/*
 * Temporizador de estudio (Pomodoro y variantes).
 *
 * Dónde se guarda cada cosa:
 *  - Configuración (tiempos, estilo, tarea, contexto, tema): localStorage. Es de un solo usuario y
 *    un solo navegador, y solo sirve para precargar el formulario.
 *  - Estado de la sesión en curso (fase, marcas de tiempo, pausas): localStorage. Así un refresco
 *    o cerrar la pestaña no lo pierde, y al volver se recalcula contra el reloj.
 *  - Lo que ya ocurrió (sesión e intervalos): el servidor, con reintentos si no hay red.
 * La lógica de fases está en pomodoro-logica.js.
 */
import {
    avanzar, crearEstado, DESCANSO, estaPausado, FOCO, formatearTiempo, formatearTranscurrido,
    iniciarSiguienteFoco, LIBRE, pausar, reanudar, reiniciar, restanteMs, saltar, terminar, transcurridoMs,
} from './pomodoro-logica.js';

const CLAVE_CONFIG = 'focodiario.estudio.config';
const CLAVE_ESTADO = 'focodiario.estudio.estado';
const CLAVE_PENDIENTES = 'focodiario.estudio.pendientes';
const CLAVE_SONIDO = 'focodiario.estudio.sonido';
const CONFIG_INICIAL = { foco: 25, descanso: 5, largo: 15, ciclos: 4, estilo: 'clasico', tarea_id: '', contexto_id: '', tema: '' };

function leer(clave, porDefecto) {
    try {
        const crudo = window.localStorage.getItem(clave);

        return crudo === null ? porDefecto : JSON.parse(crudo);
    } catch {
        return porDefecto;
    }
}

function escribir(clave, valor) {
    try {
        if (valor === null || valor === undefined) window.localStorage.removeItem(clave);
        else window.localStorage.setItem(clave, JSON.stringify(valor));
    } catch {
        // Sin almacenamiento: el temporizador sigue funcionando, solo que sin recordar.
    }
}

const config = () => ({ ...CONFIG_INICIAL, ...leer(CLAVE_CONFIG, {}) });

/* ---------- Tarjeta de Hoy: solo muestra el último ajuste ---------- */
function resumenEnHoy() {
    const destino = document.querySelector('[data-pomodoro-resumen]');

    if (!destino) return;

    const c = config();
    destino.textContent = `${String(c.foco).padStart(2, '0')}:00`;
    const detalle = document.querySelector('[data-pomodoro-resumen-detalle]');

    if (detalle) detalle.textContent = `Foco ${c.foco} min, descanso ${c.descanso} min`;
}

/* ---------- Temporizador en la vista Estudio ---------- */
function iniciarTemporizador(raiz) {
    const q = (selector) => raiz.querySelector(selector);
    const campos = {
        preset: q('#p-preset'), foco: q('#p-foco'), descanso: q('#p-descanso'), largo: q('#p-largo'),
        ciclos: q('#p-ciclos'), tarea: q('#p-tarea'), contexto: q('#p-contexto'), tema: q('#p-tema'),
    };
    const presets = JSON.parse(raiz.dataset.presets);
    const urlSesiones = raiz.dataset.urlSesiones;
    const tituloOriginal = document.title;
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
    const ahoraMs = () => Date.now();

    let estado = leer(CLAVE_ESTADO, null);
    let pendientes = leer(CLAVE_PENDIENTES, []);
    let enVuelo = false;
    let contextoAudio = null;
    let temporizador = null;

    /* --- Sonido y avisos --- */
    const sonidoActivo = () => leer(CLAVE_SONIDO, true) === true;

    function sonar() {
        if (!sonidoActivo()) return;

        try {
            contextoAudio ??= new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.22].forEach((retraso) => {
                const oscilador = contextoAudio.createOscillator();
                const volumen = contextoAudio.createGain();
                const inicio = contextoAudio.currentTime + retraso;

                oscilador.type = 'sine';
                oscilador.frequency.value = 880;
                volumen.gain.setValueAtTime(0.0001, inicio);
                volumen.gain.exponentialRampToValueAtTime(0.2, inicio + 0.02);
                volumen.gain.exponentialRampToValueAtTime(0.0001, inicio + 0.18);
                oscilador.connect(volumen).connect(contextoAudio.destination);
                oscilador.start(inicio);
                oscilador.stop(inicio + 0.2);
            });
        } catch {
            // El navegador no permite audio: se sigue sin sonido.
        }
    }

    function avisar(titulo, cuerpo) {
        sonar();

        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                new Notification(titulo, { body: cuerpo });
            } catch {
                // Algunos navegadores móviles exigen un service worker: se ignora.
            }
        }
    }

    function avisarPorEventos(eventos) {
        const ultimo = eventos.filter((e) => e.completado).at(-1);

        if (!ultimo) return;

        if (ultimo.tipo === FOCO) avisar('Foco terminado', 'Hora de descansar.');
        else if (ultimo.tipo === DESCANSO) avisar('Terminó el descanso', 'Iniciá el siguiente foco cuando estés listo.');
    }

    /* --- Comunicación con el servidor --- */
    const encabezados = () => ({
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf(),
    });

    async function vaciarPendientes() {
        if (enVuelo) return;

        enVuelo = true;

        try {
            while (pendientes.length > 0) {
                const { sesionId, evento } = pendientes[0];
                const cuerpo = {
                    ...evento,
                    inicio: new Date(evento.inicio).toISOString(),
                    fin: new Date(evento.fin).toISOString(),
                };
                const respuesta = await fetch(`${urlSesiones}/${sesionId}/intervalos`, {
                    method: 'POST', headers: encabezados(), body: JSON.stringify(cuerpo),
                });

                // Errores del cliente (sesión borrada, datos inválidos) no se arreglan reintentando.
                if (respuesta.ok || (respuesta.status >= 400 && respuesta.status < 500 && respuesta.status !== 419)) {
                    pendientes.shift();
                    escribir(CLAVE_PENDIENTES, pendientes);
                } else {
                    break;
                }
            }
        } catch {
            // Sin red: quedan en la cola y se reintentan.
        } finally {
            enVuelo = false;
        }
    }

    function encolar(eventos) {
        if (!estado && eventos.length === 0) return;

        const sesionId = estado?.sesionId ?? sesionAnterior;

        eventos.forEach((evento) => pendientes.push({ sesionId, evento }));
        escribir(CLAVE_PENDIENTES, pendientes);
        vaciarPendientes();
    }

    let sesionAnterior = estado?.sesionId ?? null;

    /* --- Formulario --- */
    function mostrarErrores(mensajes) {
        const caja = q('[data-p="errores"]');

        caja.hidden = mensajes.length === 0;
        caja.replaceChildren(...mensajes.map((texto) => Object.assign(document.createElement('div'), { textContent: texto })));
    }

    function aplicarConfig(c) {
        campos.foco.value = c.foco;
        campos.descanso.value = c.descanso;
        campos.largo.value = c.largo;
        campos.ciclos.value = c.ciclos;
        campos.preset.value = c.estilo;
        campos.tarea.value = c.tarea_id ?? '';
        campos.contexto.value = c.contexto_id ?? '';
        campos.tema.value = c.tema ?? '';
    }

    function leerFormulario() {
        return {
            foco: Number(campos.foco.value), descanso: Number(campos.descanso.value),
            largo: Number(campos.largo.value), ciclos: Number(campos.ciclos.value),
            estilo: campos.preset.value, tarea_id: campos.tarea.value, contexto_id: campos.contexto.value,
            tema: campos.tema.value.trim(),
        };
    }

    function detectarEstilo() {
        const c = leerFormulario();
        const coincide = Object.entries(presets).find(([, p]) => p.foco === c.foco && p.descanso === c.descanso && p.largo === c.largo && p.ciclos === c.ciclos);

        campos.preset.value = coincide ? coincide[0] : 'personalizado';
        escribir(CLAVE_CONFIG, leerFormulario());
    }

    campos.preset.addEventListener('change', () => {
        const preset = presets[campos.preset.value];

        if (preset) {
            campos.foco.value = preset.foco;
            campos.descanso.value = preset.descanso;
            campos.largo.value = preset.largo;
            campos.ciclos.value = preset.ciclos;
        }

        escribir(CLAVE_CONFIG, leerFormulario());
    });
    [campos.foco, campos.descanso, campos.largo, campos.ciclos].forEach((c) => c.addEventListener('input', detectarEstilo));
    [campos.tarea, campos.contexto, campos.tema].forEach((c) => c.addEventListener('change', () => escribir(CLAVE_CONFIG, leerFormulario())));

    /* --- Dibujo --- */
    const NOMBRES_FASE = { [FOCO]: 'Foco', [DESCANSO]: 'Descanso', [LIBRE]: 'Tiempo libre' };

    function dibujar() {
        const ahora = ahoraMs();
        const activo = estado !== null;
        const c = activo ? estado.config : leerFormulario();

        raiz.dataset.fase = activo ? estado.fase : 'inactivo';
        raiz.dataset.pausado = activo && estaPausado(estado) ? 'true' : 'false';
        q('#p-formulario').disabled = activo;
        q('[data-p="resumen-sesion"]').hidden = !activo;

        let texto = formatearTiempo(c.foco * 60_000);
        let etiqueta = 'Listo para empezar';
        let ayuda = 'Elegí los tiempos y qué vas a trabajar. El tiempo sigue corriendo aunque cambies de pestaña.';

        if (activo) {
            if (estado.fase === LIBRE) {
                texto = formatearTranscurrido(transcurridoMs(estado, ahora));
                etiqueta = 'Tiempo libre';
                ayuda = 'El descanso terminó. Este tiempo se registra hasta que empieces el siguiente foco.';
            } else {
                texto = formatearTiempo(restanteMs(estado, ahora));
                etiqueta = estado.fase === FOCO
                    ? 'Foco'
                    : (estado.descansoLargo ? 'Descanso largo' : 'Descanso corto');
                ayuda = estaPausado(estado)
                    ? 'En pausa. El tiempo en pausa no cuenta como foco ni como descanso.'
                    : (estado.fase === FOCO ? 'Una sola tarea, sin distracciones.' : 'Pararte, tomar agua y mirar lejos. Sin celular.');
            }

            const enFoco = estado.fase === FOCO;
            const numero = Math.min(estado.enCiclo + (enFoco ? 1 : 0), estado.config.ciclos);

            q('[data-p="ciclo"]').textContent = `Pomodoro ${Math.max(numero, 1)} de ${estado.config.ciclos}`;
            q('[data-p="puntos"]').replaceChildren(...Array.from({ length: estado.config.ciclos }, (_, i) => {
                const punto = document.createElement('span');

                punto.className = `pomodoro-punto${i < estado.enCiclo ? ' lleno' : ''}${enFoco && i === estado.enCiclo ? ' actual' : ''}`;

                return punto;
            }));
            q('[data-p="completados"]').textContent = estado.completados;
            q('[data-p="interrumpidos"]').textContent = estado.interrumpidos;
            q('[data-p="descanso"]').textContent = minutos(estado.totalDescansoSeg + (estado.fase === DESCANSO ? transcurridoMs(estado, ahora) / 1000 : 0));
            q('[data-p="libre"]').textContent = minutos(estado.totalLibreSeg + (estado.fase === LIBRE ? transcurridoMs(estado, ahora) / 1000 : 0));
            q('[data-p="detalle"]').textContent = detalleSesion();
        } else {
            q('[data-p="ciclo"]').textContent = '';
            q('[data-p="puntos"]').replaceChildren();
        }

        q('[data-p="tiempo"]').textContent = texto;
        q('[data-p="fase"]').textContent = etiqueta;
        q('[data-p="ayuda"]').textContent = ayuda;

        raiz.querySelectorAll('[data-p-accion]').forEach((boton) => {
            const visibles = boton.dataset.visible.split(' ');
            const clave = !activo ? 'inactivo' : (estaPausado(estado) ? `${estado.fase}-pausa` : estado.fase);

            boton.hidden = !visibles.includes(clave);
        });

        document.title = activo ? `${texto} · ${NOMBRES_FASE[estado.fase]} · FocoDiario` : tituloOriginal;
    }

    const minutos = (seg) => `${Math.round(seg / 60)} min`;

    function detalleSesion() {
        const partes = [];

        if (campos.tema.value) partes.push(campos.tema.value);
        if (campos.tarea.selectedOptions[0]?.value) partes.push(campos.tarea.selectedOptions[0].textContent.trim());
        if (campos.contexto.selectedOptions[0]?.value) partes.push(campos.contexto.selectedOptions[0].textContent.trim());

        return partes.join(' · ');
    }

    /* --- Ciclo de vida --- */
    function guardar() {
        escribir(CLAVE_ESTADO, estado);
    }

    function tic() {
        if (estado) {
            const resultado = avanzar(estado, ahoraMs());

            estado = resultado.estado;

            if (resultado.eventos.length > 0) {
                guardar();
                encolar(resultado.eventos);
                avisarPorEventos(resultado.eventos);
            }
        }

        if (pendientes.length > 0 && !enVuelo && ahoraMs() % 30_000 < 300) vaciarPendientes();

        dibujar();
    }

    function programar() {
        clearInterval(temporizador);
        temporizador = setInterval(tic, 250);
    }

    async function iniciar() {
        mostrarErrores([]);

        const formulario = q('#p-formulario');
        const invalido = [...formulario.querySelectorAll('input[type="number"]')].find((campo) => !campo.checkValidity());

        if (invalido) {
            mostrarErrores([`${invalido.dataset.nombre}: ${invalido.validationMessage}`]);
            invalido.focus();

            return;
        }

        const c = leerFormulario();
        const boton = q('[data-p-accion="iniciar"]');

        boton.disabled = true;
        // Crear el contexto de audio dentro del clic, para que el navegador permita sonar después.
        try { contextoAudio ??= new (window.AudioContext || window.webkitAudioContext)(); } catch { /* sin audio */ }

        try {
            const respuesta = await fetch(urlSesiones, {
                method: 'POST',
                headers: encabezados(),
                body: JSON.stringify({
                    tarea_id: c.tarea_id || null, contexto_id: c.contexto_id || null, tema: c.tema || null,
                    estilo: c.estilo, foco_min: c.foco, descanso_min: c.descanso,
                    descanso_largo_min: c.largo, pomodoros_antes_largo: c.ciclos,
                }),
            });
            const datos = await respuesta.json().catch(() => ({}));

            if (!respuesta.ok) {
                mostrarErrores(datos.errors ? Object.values(datos.errors).flat() : ['No se pudo iniciar la sesión. Probá de nuevo.']);

                return;
            }

            escribir(CLAVE_CONFIG, c);
            sesionAnterior = datos.id;
            estado = crearEstado(datos.id, { foco: c.foco, descanso: c.descanso, largo: c.largo, ciclos: c.ciclos }, ahoraMs());
            guardar();
        } catch {
            mostrarErrores(['No se pudo conectar con el servidor. Revisá tu conexión y probá de nuevo.']);
        } finally {
            boton.disabled = false;
            dibujar();
        }
    }

    async function finalizarEnServidor(sesionId) {
        await vaciarPendientes();

        try {
            await fetch(`${urlSesiones}/${sesionId}/finalizar`, { method: 'PATCH', headers: encabezados() });
        } catch {
            // Si falla, la sesión queda "en curso" y se cierra sola al iniciar la próxima.
        }
    }

    function aplicar(resultado) {
        estado = resultado.estado;
        guardar();
        encolar(resultado.eventos);
        dibujar();
    }

    const acciones = {
        iniciar,
        pausar: () => { estado = pausar(estado, ahoraMs()); guardar(); dibujar(); },
        reanudar: () => { estado = reanudar(estado, ahoraMs()); guardar(); dibujar(); },
        reiniciar: () => { estado = reiniciar(estado, ahoraMs()); guardar(); dibujar(); },
        saltar: () => aplicar(saltar(estado, ahoraMs())),
        'siguiente-foco': () => aplicar(iniciarSiguienteFoco(estado, ahoraMs())),
        terminar: async () => {
            if (!window.confirm('¿Terminar la sesión? Lo que está en curso se registra tal como está.')) return;

            const sesionId = estado.sesionId;
            const resultado = terminar(estado, ahoraMs());

            estado = null;
            escribir(CLAVE_ESTADO, null);
            sesionAnterior = sesionId;
            encolar(resultado.eventos);
            dibujar();
            await finalizarEnServidor(sesionId);
            mostrarErrores([]);
            q('[data-p="terminada"]').hidden = false;
        },
    };

    raiz.querySelectorAll('[data-p-accion]').forEach((boton) => {
        boton.addEventListener('click', () => {
            q('[data-p="terminada"]').hidden = true;
            acciones[boton.dataset.pAccion]?.();
        });
    });

    // Sonido y notificaciones
    const casillaSonido = q('#p-sonido');
    const botonNotificar = q('[data-p="notificar"]');

    casillaSonido.checked = sonidoActivo();
    casillaSonido.addEventListener('change', () => escribir(CLAVE_SONIDO, casillaSonido.checked));

    function estadoNotificaciones() {
        if (!('Notification' in window)) {
            botonNotificar.hidden = true;

            return;
        }

        botonNotificar.hidden = Notification.permission !== 'default';
    }

    botonNotificar.addEventListener('click', async () => {
        await Notification.requestPermission();
        estadoNotificaciones();
    });
    estadoNotificaciones();

    // Al volver a la pestaña se recalcula al instante, sin esperar el siguiente tic.
    document.addEventListener('visibilitychange', tic);
    window.addEventListener('focus', tic);

    /* --- Arranque --- */
    const sesionActivaServidor = raiz.dataset.sesionActiva ? Number(raiz.dataset.sesionActiva) : null;

    aplicarConfig(config());

    if (estado && estado.sesionId !== sesionActivaServidor) {
        // La sesión guardada ya no está en curso en el servidor (se borró o se cerró): se descarta.
        estado = null;
        escribir(CLAVE_ESTADO, null);
    } else if (!estado && sesionActivaServidor) {
        // Quedó una sesión abierta sin estado local (otro navegador o datos borrados): se cierra.
        finalizarEnServidor(sesionActivaServidor);
    }

    if (!estado) {
        const preset = new URLSearchParams(window.location.search).get('preset');

        if (preset && presets[preset]) {
            campos.preset.value = preset;
            campos.preset.dispatchEvent(new Event('change'));
        }
    }

    programar();
    vaciarPendientes();
    tic();
}

document.addEventListener('DOMContentLoaded', () => {
    resumenEnHoy();

    const raiz = document.getElementById('pomodoro');

    if (raiz) iniciarTemporizador(raiz);
});
