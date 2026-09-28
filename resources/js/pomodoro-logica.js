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
 * en el servidor { tipo, clave, inicio, fin, planificado_min, pausado_seg, completado }.
 */

export const FOCO = 'foco';
export const DESCANSO = 'descanso';
export const LIBRE = 'libre';

const MS = 1000;

/** Estado nuevo de una sesión: arranca directamente en foco. */
export function crearEstado(sesionId, config, ahora) {
    return iniciarFase(
        {
            sesionId,
            config: { ...config },
            completados: 0,
            interrumpidos: 0,
            enCiclo: 0, // pomodoros completos desde el último descanso largo
            seq: 0,
            descansoLargo: false,
            totalDescansoSeg: 0,
            totalLibreSeg: 0,
        },
        FOCO,
        ahora,
    );
}

function planificadoSeg(estado, fase) {
    if (fase === FOCO) return estado.config.foco * 60;
    if (fase === DESCANSO) return (estado.descansoLargo ? estado.config.largo : estado.config.descanso) * 60;
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
        planificado_min: estado.fase === LIBRE ? null : estado.planificadoSeg / 60,
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
