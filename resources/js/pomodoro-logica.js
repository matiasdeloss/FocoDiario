/*
 * Lógica de fases del temporizador, sin DOM ni red, para poder probarla con Node.
 *
 * El tiempo nunca se cuenta con ticks: cada función recibe "ahora" (milisegundos) y
 * calcula contra las marcas de tiempo guardadas. Si la pestaña queda en segundo plano
 * o se recarga, al volver se reconstruye todo desde esas marcas.
 *
 * Fases:
 *  - foco:     cuenta regresiva.
 *  - descanso: cuenta regresiva (corto o largo). Al llegar a cero empieza el tiempo libre.
 *  - libre:    cuenta hacia arriba hasta que la persona inicia el siguiente foco.
 *
 * Cada vez que termina una fase se genera un "evento": el intervalo que hay que registrar
 * en el servidor { tipo, clave, inicio, fin, planificado_seg, pausado_seg, completado }.
 */

export const FOCO = 'foco';
export const DESCANSO = 'descanso';
export const LIBRE = 'libre';

const MS = 1000;

/** Rango válido de cada duración, en segundos: de 5 s a 180 min. */
export const SEG_MINIMO = 5;
export const SEG_MAXIMO = 10800;
export const CICLOS_MINIMO = 2;
export const CICLOS_MAXIMO = 12;

/** Configuración por defecto (Pomodoro clásico). Los tiempos van en segundos. */
export const CONFIG_POR_DEFECTO = { foco: 1500, descanso: 300, largo: 900, ciclos: 4, estilo: 'clasico', tarea_id: '', contexto_id: '', tema: '' };

const esDuracionValida = (n) => Number.isInteger(n) && n >= SEG_MINIMO && n <= SEG_MAXIMO;

/**
 * Configuración guardada por versiones anteriores, que guardaba foco/descanso/largo en minutos
 * (sin la marca `seg`). Se convierte a segundos; lo que no sea un valor válido vuelve al de por defecto.
 * Si ya viene en segundos (`seg: true`) solo se sanean los valores.
 */
export function migrarConfig(guardada) {
    const base = { ...CONFIG_POR_DEFECTO };

    if (guardada === null || typeof guardada !== 'object' || Array.isArray(guardada)) return base;

    const factor = guardada.seg === true ? 1 : 60;
    const config = { ...base };

    for (const clave of ['foco', 'descanso', 'largo']) {
        const valor = Number(guardada[clave]) * factor;

        if (guardada[clave] !== null && guardada[clave] !== '' && Number.isInteger(Number(guardada[clave])) && esDuracionValida(valor)) config[clave] = valor;
    }

    const ciclos = Number(guardada.ciclos);

    if (Number.isInteger(ciclos) && ciclos >= CICLOS_MINIMO && ciclos <= CICLOS_MAXIMO) config.ciclos = ciclos;

    for (const clave of ['estilo', 'tarea_id', 'contexto_id', 'tema']) {
        if (typeof guardada[clave] === 'string' || typeof guardada[clave] === 'number') config[clave] = guardada[clave];
    }

    return config;
}

/** Estado de una sesión en curso guardado por versiones anteriores (config en minutos): se pasa a segundos. */
export function migrarEstado(estado) {
    if (estado === null || typeof estado !== 'object' || !estado.config || estado.config.seg === true) return estado;

    const { foco, descanso, largo, ciclos } = estado.config;

    return { ...estado, config: { foco: foco * 60, descanso: descanso * 60, largo: largo * 60, ciclos, seg: true } };
}

/** Intervalo pendiente de envío guardado por versiones anteriores (planificado_min): se pasa a planificado_seg. */
export function migrarEvento(evento) {
    if (!evento || !('planificado_min' in evento)) return evento;

    const { planificado_min: minutos, ...resto } = evento;

    return { ...resto, planificado_seg: minutos === null || minutos === undefined ? null : minutos * 60 };
}

/**
 * Valida una duración escrita como minutos y segundos. Los campos vacíos cuentan como 0.
 * Devuelve { ok: true, seg } o { ok: false, error } con un mensaje en español.
 */
export function validarDuracion(min, seg, nombre = 'La duración') {
    const texto = (v) => (v === '' || v === null || v === undefined ? '0' : String(v).trim());
    const m = Number(texto(min));
    const s = Number(texto(seg));

    if (!Number.isInteger(m) || !Number.isInteger(s) || m < 0 || s < 0) {
        return { ok: false, error: `${nombre}: escribí minutos y segundos como números enteros, sin decimales ni negativos.` };
    }

    if (s > 59) return { ok: false, error: `${nombre}: los segundos van de 0 a 59. Para más de un minuto usá el campo de minutos.` };

    const total = m * 60 + s;

    if (total < SEG_MINIMO || total > SEG_MAXIMO) {
        return { ok: false, error: `${nombre}: la duración debe estar entre 00:05 y 180:00 (min:seg).` };
    }

    return { ok: true, seg: total };
}

/** Parte una cantidad de segundos en { min, seg } para los dos campos del formulario. */
export const dividirSegundos = (total) => ({ min: Math.floor(total / 60), seg: total % 60 });

/** Texto corto de una duración en segundos: "5 s", "1 min 30 s", "45 min", "2 h 05 min". Mismo criterio que el servidor. */
export function formatearDuracion(total) {
    const t = Math.max(0, Math.round(total));

    if (t < 60) return `${t} s`;

    if (t < 3600) return t % 60 === 0 ? `${t / 60} min` : `${Math.floor(t / 60)} min ${t % 60} s`;

    const minutos = Math.round(t / 60);
    const resto = minutos % 60;

    return resto === 0 ? `${Math.floor(minutos / 60)} h` : `${Math.floor(minutos / 60)} h ${String(resto).padStart(2, '0')} min`;
}

/**
 * Estado nuevo de una sesión. Por defecto arranca en foco; `desde` permite arrancar directamente
 * en un descanso corto ('descanso') o largo ('largo'), como hace la tarjeta de Hoy.
 */
export function crearEstado(sesionId, config, ahora, desde = 'foco') {
    const enDescanso = desde === 'descanso' || desde === 'largo';

    return iniciarFase(
        {
            sesionId,
            config: { ...config, seg: true },
            completados: 0,
            interrumpidos: 0,
            enCiclo: 0, // pomodoros completos desde el último descanso largo
            seq: 0,
            descansoLargo: desde === 'largo',
            totalDescansoSeg: 0,
            totalLibreSeg: 0,
        },
        enDescanso ? DESCANSO : FOCO,
        ahora,
    );
}

function planificadoSeg(estado, fase) {
    if (fase === FOCO) return estado.config.foco;
    if (fase === DESCANSO) return estado.descansoLargo ? estado.config.largo : estado.config.descanso;
    return 0;
}

function iniciarFase(estado, fase, inicio) {
    return {
        ...estado,
        fase,
        faseInicio: inicio,
        planificadoSeg: planificadoSeg(estado, fase),
        pausadoDesde: null,
        pausadoAcumMs: 0,
    };
}

/** Milisegundos efectivos de la fase actual (sin las pausas). */
export function transcurridoMs(estado, ahora) {
    const referencia = estado.pausadoDesde ?? ahora;

    return Math.max(0, referencia - estado.faseInicio - estado.pausadoAcumMs);
}

/** Milisegundos que faltan en foco o descanso; en libre es 0. */
export function restanteMs(estado, ahora) {
    if (estado.fase === LIBRE) return 0;

    return Math.max(0, estado.planificadoSeg * MS - transcurridoMs(estado, ahora));
}

export function estaPausado(estado) {
    return estado.pausadoDesde !== null;
}

function pausadoSeg(estado, fin) {
    const enPausa = estado.pausadoDesde !== null ? Math.max(0, fin - estado.pausadoDesde) : 0;

    return Math.round((estado.pausadoAcumMs + enPausa) / MS);
}

function crearEvento(estado, fin, completado) {
    const seq = estado.seq + 1;
    const duracionSeg = Math.max(0, Math.round((fin - estado.faseInicio) / MS) - pausadoSeg(estado, fin));
    const evento = {
        tipo: estado.fase,
        clave: `${estado.sesionId}-${seq}`,
        inicio: estado.faseInicio,
        fin,
        planificado_seg: estado.fase === LIBRE ? null : estado.planificadoSeg,
        pausado_seg: pausadoSeg(estado, fin),
        completado,
    };

    return { evento, seq, duracionSeg };
}

/** Suma el tiempo real de descanso y de libre para mostrarlo en el resumen de la sesión. */
function acumular(estado, evento, duracionSeg) {
    if (evento.tipo === DESCANSO) return { ...estado, totalDescansoSeg: estado.totalDescansoSeg + duracionSeg };
    if (evento.tipo === LIBRE) return { ...estado, totalLibreSeg: estado.totalLibreSeg + duracionSeg };

    return estado;
}

/**
 * Cierra las fases que ya llegaron a cero (aunque hayan pasado horas con la pestaña cerrada).
 * El fin de cada fase es su hora teórica, no "ahora", así el registro no se distorsiona.
 * Una fase en pausa no avanza.
 *
 * @returns {{ estado: object, eventos: object[] }}
 */
export function avanzar(estado, ahora) {
    const eventos = [];
    let actual = estado;

    while (actual.fase !== LIBRE && !estaPausado(actual) && restanteMs(actual, ahora) <= 0) {
        const fin = actual.faseInicio + actual.pausadoAcumMs + actual.planificadoSeg * MS;
        const { evento, seq, duracionSeg } = crearEvento(actual, fin, true);

        eventos.push(evento);
        actual = acumular({ ...actual, seq }, evento, duracionSeg);

        if (evento.tipo === FOCO) {
            const enCiclo = actual.enCiclo + 1;
            const largo = enCiclo >= actual.config.ciclos;

            actual = iniciarFase(
                { ...actual, completados: actual.completados + 1, enCiclo: largo ? 0 : enCiclo, descansoLargo: largo },
                DESCANSO,
                fin,
            );
        } else {
            actual = iniciarFase(actual, LIBRE, fin);
        }
    }

    return { estado: actual, eventos };
}

export function pausar(estado, ahora) {
    if (estado.fase === LIBRE || estaPausado(estado)) return estado;

    return { ...estado, pausadoDesde: ahora };
}

export function reanudar(estado, ahora) {
    if (!estaPausado(estado)) return estado;

    return { ...estado, pausadoAcumMs: estado.pausadoAcumMs + (ahora - estado.pausadoDesde), pausadoDesde: null };
}

/** Vuelve a empezar la fase actual sin registrar nada (foco o descanso). */
export function reiniciar(estado, ahora) {
    if (estado.fase === LIBRE) return estado;

    return { ...estado, faseInicio: ahora, pausadoDesde: null, pausadoAcumMs: 0 };
}

/**
 * Saltar la fase actual:
 *  - foco: queda como interrumpido (con su duración real) y sigue un descanso corto.
 *  - descanso: se registra el descanso real (más corto que lo previsto) y empieza el siguiente foco.
 *  - libre: se registra el tiempo libre y empieza el siguiente foco.
 */
export function saltar(estado, ahora) {
    const { estado: base, eventos } = avanzar(estado, ahora);

    return cerrarFase(base, ahora, eventos, true);
}

/** Termina la sesión: registra lo que estaba en curso y devuelve estado null. */
export function terminar(estado, ahora) {
    const { estado: base, eventos } = avanzar(estado, ahora);
    const cierre = cerrarFase(base, ahora, eventos, false);

    return { estado: null, eventos: cierre.eventos, resumen: cierre.estado ?? base };
}

function cerrarFase(base, ahora, eventosPrevios, continuar) {
    const eventos = [...eventosPrevios];
    let actual = base;
    // Foco y descanso cortados a mano quedan sin completar; el tiempo libre siempre es "completo".
    const { evento, seq, duracionSeg } = crearEvento(actual, ahora, actual.fase === LIBRE);

    // Un intervalo de cero segundos no se registra (por ejemplo, terminar apenas iniciado).
    if (duracionSeg > 0) {
        eventos.push(evento);
        actual = acumular({ ...actual, seq }, evento, duracionSeg);
    }

    if (actual.fase === FOCO) {
        if (duracionSeg > 0) actual = { ...actual, interrumpidos: actual.interrumpidos + 1 };
        actual = { ...actual, descansoLargo: false };
        if (continuar) actual = iniciarFase(actual, DESCANSO, ahora);
    } else if (continuar) {
        actual = iniciarFase(actual, FOCO, ahora);
    }

    return { estado: actual, eventos };
}

/** Inicia el siguiente foco desde el tiempo libre (o saltando el descanso). */
export function iniciarSiguienteFoco(estado, ahora) {
    if (estado.fase === FOCO) return { estado, eventos: [] };

    return saltar(estado, ahora);
}

/** Texto mm:ss (o h:mm:ss) de una cantidad de milisegundos. */
export function formatearTiempo(ms) {
    const total = Math.max(0, Math.ceil(ms / MS));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const dos = (n) => String(n).padStart(2, '0');

    return h > 0 ? `${h}:${dos(m)}:${dos(s)}` : `${dos(m)}:${dos(s)}`;
}

/** Tiempo del tiempo libre: cuenta hacia arriba, se redondea hacia abajo. */
export function formatearTranscurrido(ms) {
    const total = Math.max(0, Math.floor(ms / MS));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const dos = (n) => String(n).padStart(2, '0');

    return h > 0 ? `${h}:${dos(m)}:${dos(s)}` : `${dos(m)}:${dos(s)}`;
}

/**
 * Datos listos para mostrar de una fase (los usan la tarjeta de Estudio y el mini-temporizador):
 * nombre completo, nombre corto, texto del tiempo y progreso 0..1 (null en el tiempo libre).
 */
export function describir(estado, ahora) {
    const pausado = estaPausado(estado);

    if (estado.fase === LIBRE) {
        return {
            fase: LIBRE, nombre: 'Tiempo libre', corta: 'Libre', pausado: false, progreso: null,
            texto: formatearTranscurrido(transcurridoMs(estado, ahora)),
        };
    }

    const total = estado.planificadoSeg * MS;
    const progreso = total > 0 ? Math.min(1, transcurridoMs(estado, ahora) / total) : 0;

    return {
        fase: estado.fase,
        nombre: estado.fase === FOCO ? 'Foco' : (estado.descansoLargo ? 'Descanso largo' : 'Descanso corto'),
        corta: estado.fase === FOCO ? 'Foco' : 'Descanso',
        pausado,
        progreso,
        texto: formatearTiempo(restanteMs(estado, ahora)),
    };
}

/**
 * Fracción 0..1 del anillo de progreso: lo que queda de la fase actual (1 = anillo completo, 0 = vacío).
 * Sin sesión o en tiempo libre (no tiene fin) el anillo queda completo. Una fase en pausa se queda donde estaba.
 */
export function fraccionAnillo(estado, ahora) {
    if (!estado || estado.fase === LIBRE) return 1;

    const total = estado.planificadoSeg * MS;

    if (!(total > 0)) return 1;

    return Math.min(1, Math.max(0, 1 - transcurridoMs(estado, ahora) / total));
}

/** stroke-dashoffset de un anillo de circunferencia `circunferencia` que muestra `fraccion` (0..1) del arco. */
export function desplazamientoAnillo(fraccion, circunferencia) {
    const f = Number.isFinite(fraccion) ? Math.min(1, Math.max(0, fraccion)) : 1;

    return circunferencia * (1 - f);
}

const FORMATOS_TIEMPO = 'Probá con 25, 25:00, 1:30, 90s o 1:05:00.';

/**
 * Interpreta lo que se escribe al editar el reloj. Reglas:
 *  - un número solo son minutos ("25" = 25 min);
 *  - con "s" son segundos ("90s", "5s") y con "m" minutos ("25m", "1m30s");
 *  - con dos puntos es mm:ss ("1:30") o h:mm:ss ("1:05:00"); después de los dos puntos van 00 a 59.
 * Valida el rango de 5 s a 180 min. Devuelve { ok: true, seg } o { ok: false, error } con un mensaje breve.
 */
export function interpretarTiempo(texto) {
    const t = String(texto ?? '').trim().toLowerCase().replace(/\s+/g, '');
    const noValido = { ok: false, error: `No entiendo ese tiempo. ${FORMATOS_TIEMPO}` };
    const restoInvalido = { ok: false, error: 'Después de los dos puntos, minutos y segundos van de 00 a 59.' };
    let m;
    let total;

    if ((m = /^(\d{1,6})$/.exec(t))) {
        total = Number(m[1]) * 60;
    } else if ((m = /^(\d{1,6})(?:s|seg)$/.exec(t))) {
        total = Number(m[1]);
    } else if ((m = /^(\d{1,6})(?:m|min)$/.exec(t))) {
        total = Number(m[1]) * 60;
    } else if ((m = /^(\d{1,6})(?:m|min)(\d{1,2})(?:s|seg)?$/.exec(t))) {
        if (Number(m[2]) > 59) return restoInvalido;

        total = Number(m[1]) * 60 + Number(m[2]);
    } else if ((m = /^(\d{1,4}):(\d{1,2})$/.exec(t))) {
        if (Number(m[2]) > 59) return restoInvalido;

        total = Number(m[1]) * 60 + Number(m[2]);
    } else if ((m = /^(\d{1,3}):(\d{1,2}):(\d{1,2})$/.exec(t))) {
        if (Number(m[2]) > 59 || Number(m[3]) > 59) return restoInvalido;

        total = Number(m[1]) * 3600 + Number(m[2]) * 60 + Number(m[3]);
    } else {
        return noValido;
    }

    if (total < SEG_MINIMO || total > SEG_MAXIMO) return { ok: false, error: 'El tiempo va de 00:05 a 180:00.' };

    return { ok: true, seg: total };
}
