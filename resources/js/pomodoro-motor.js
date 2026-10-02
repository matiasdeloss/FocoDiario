/*
 * Motor único del temporizador de estudio. Corre una sola vez por página (es un módulo: todos
 * los que lo importan comparten la misma instancia) y lo usan tanto la tarjeta de /estudio como
 * el mini-temporizador de la barra superior.
 *
 * Fuente de verdad: localStorage (estado de la sesión y cola de intervalos pendientes).
 * Entre pestañas:
 *  - el evento `storage` sincroniza el estado en las demás pestañas;
 *  - un candado con caducidad (pomodoro-candado.js) deja que solo una pestaña cierre fases y envíe
 *    intervalos al servidor. Si dos llegaran a coincidir, la clave idempotente del servidor descarta
 *    el duplicado.
 * La lógica de fases está en pomodoro-logica.js.
 */
import { adquirirCandado, liberarCandado } from './pomodoro-candado.js';
import { pedirSeguro } from './red.js';
import {
    avanzar, CONFIG_POR_DEFECTO, crearEstado, DESCANSO, describir, FOCO, iniciarSiguienteFoco, migrarConfig, migrarEstado,
    migrarEvento, pausar, reanudar, reiniciar, saltar, sonidoValido, terminar, TONOS_SONIDO,
} from './pomodoro-logica.js';
import { confirmar } from './confirmar.js';

export const CLAVE_CONFIG = 'focodiario.estudio.config.v2';
/** Clave de las versiones anteriores, que guardaban los tiempos en minutos. Se lee una vez, se convierte y se borra. */
const CLAVE_CONFIG_ANTIGUA = 'focodiario.estudio.config';
export const CLAVE_ESTADO = 'focodiario.estudio.estado';
export const CLAVE_PENDIENTES = 'focodiario.estudio.pendientes';
export const CLAVE_SONIDO = 'focodiario.estudio.sonido';
export const CLAVE_SONIDO_TIPO = 'focodiario.estudio.sonido-tipo';
export const CLAVE_CANDADO = 'focodiario.estudio.candado';

const ID_PESTANA = `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;

export function leer(clave, porDefecto) {
    try {
        const crudo = window.localStorage.getItem(clave);

        return crudo === null ? porDefecto : JSON.parse(crudo);
    } catch {
        return porDefecto;
    }
}

export function escribir(clave, valor) {
    try {
        if (valor === null || valor === undefined) window.localStorage.removeItem(clave);
        else window.localStorage.setItem(clave, JSON.stringify(valor));
    } catch {
        // Sin almacenamiento: el temporizador sigue funcionando, solo que sin recordar.
    }
}

export const CONFIG_INICIAL = CONFIG_POR_DEFECTO;

/**
 * Última configuración guardada (tiempos en segundos, estilo, tarea, contexto, tema), completada con los valores por defecto.
 * Si solo existe la de versiones anteriores (en minutos) se convierte y se guarda en el formato nuevo.
 */
export function configGuardada() {
    const nueva = leer(CLAVE_CONFIG, null);

    if (nueva !== null) return migrarConfig({ ...nueva, seg: true });

    const antigua = leer(CLAVE_CONFIG_ANTIGUA, null);

    if (antigua === null) return { ...CONFIG_INICIAL };

    const convertida = migrarConfig(antigua);

    escribir(CLAVE_CONFIG, { ...convertida, seg: true });
    escribir(CLAVE_CONFIG_ANTIGUA, null);

    return convertida;
}

/** Guarda la duración de una fase ('foco', 'descanso' o 'largo'), en segundos, en la configuración de siempre. */
export function guardarDuracion(clave, seg) {
    escribir(CLAVE_CONFIG, { ...configGuardada(), [clave]: seg, estilo: 'personalizado', seg: true });
}

/** URL base de las sesiones: la publica el widget (o la tarjeta de Estudio) en data-url-sesiones. */
export const urlSesiones = () => document.querySelector('[data-url-sesiones]')?.dataset.urlSesiones ?? '/estudio/sesiones';

let estado = null;
let suscriptores = [];
let vuelo = null;
let contextoAudio = null;
let temporizador = null;
let tituloOriginal = '';
let iniciado = false;

/* ---------- Candado entre pestañas ---------- */
function esLider(ahora = Date.now()) {
    try {
        return adquirirCandado(window.localStorage, CLAVE_CANDADO, ID_PESTANA, ahora);
    } catch {
        return true;
    }
}

/* ---------- Sonido y avisos ---------- */
export const sonidoActivo = () => leer(CLAVE_SONIDO, true) === true;

/** Crear el contexto de audio dentro de un clic, para que el navegador permita sonar después. */
export function prepararAudio() {
    try {
        contextoAudio ??= new (window.AudioContext || window.webkitAudioContext)();
    } catch {
        // Sin audio.
    }
}

/** Sonido elegido, leído en el momento de sonar (sin guardarlo en memoria) para que un cambio hecho en otra pestaña se aplique. */
export const sonidoElegido = () => sonidoValido(leer(CLAVE_SONIDO_TIPO, null));

/** Reproduce un sonido del catálogo. Para la vista previa hay que llamarlo dentro de un clic (crea o reanuda el contexto de audio). */
export function reproducirSonido(clave) {
    try {
        prepararAudio();
        contextoAudio.resume?.();

        const base = contextoAudio.currentTime;

        TONOS_SONIDO[sonidoValido(clave)].forEach((t) => {
            const oscilador = contextoAudio.createOscillator();
            const volumen = contextoAudio.createGain();
            const inicio = base + t.inicio;
            const fin = inicio + t.duracion;

            oscilador.type = t.onda;
            oscilador.frequency.value = t.frecuencia;
            // Ataque corto y caída hasta casi cero antes de parar, para que no haya clics.
            volumen.gain.setValueAtTime(0.0001, inicio);
            volumen.gain.exponentialRampToValueAtTime(t.ganancia, inicio + 0.02);
            volumen.gain.exponentialRampToValueAtTime(0.0001, fin - 0.02);
            oscilador.connect(volumen).connect(contextoAudio.destination);
            oscilador.start(inicio);
            oscilador.stop(fin);
        });
    } catch {
        // El navegador no permite audio: se sigue sin sonido.
    }
}

function sonar() {
    if (sonidoActivo()) reproducirSonido(sonidoElegido());
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

/* ---------- Servidor ---------- */
/**
 * Envía la cola de intervalos, uno por uno. Si ya hay un envío en curso devuelve ese mismo.
 * Solo la pestaña con el candado envía por su cuenta; `forzar` lo omite (al terminar una sesión).
 */
export function vaciarPendientes({ forzar = false } = {}) {
    if (vuelo) return vuelo;

    if (!forzar && !esLider()) return Promise.resolve();

    vuelo = (async () => {
        try {
            for (;;) {
                const cola = leer(CLAVE_PENDIENTES, []);

                if (cola.length === 0) break;

                const { sesionId } = cola[0];
                const evento = migrarEvento(cola[0].evento);
                const cuerpo = {
                    ...evento,
                    inicio: new Date(evento.inicio).toISOString(),
                    fin: new Date(evento.fin).toISOString(),
                };
                const respuesta = await pedirSeguro(`${urlSesiones()}/${sesionId}/intervalos`, {
                    method: 'POST', body: JSON.stringify(cuerpo),
                });

                // Errores del cliente (sesión borrada, datos inválidos) no se arreglan reintentando.
                if (respuesta.ok || (respuesta.status >= 400 && respuesta.status < 500 && respuesta.status !== 419)) {
                    // Se relee la cola: otra pestaña pudo haber agregado o quitado elementos mientras tanto.
                    escribir(CLAVE_PENDIENTES, leer(CLAVE_PENDIENTES, []).filter((p) => !(p.sesionId === sesionId && p.evento.clave === evento.clave)));

                    // La sesión ya no existe en el servidor: no tiene sentido seguir con ella.
                    if (respuesta.status === 404 && estado?.sesionId === sesionId) descartarEstado();
                } else {
                    break;
                }
            }
        } catch {
            // Sin red: quedan en la cola y se reintentan.
        } finally {
            vuelo = null;
        }
    })();

    return vuelo;
}

function encolar(sesionId, eventos) {
    if (eventos.length === 0) return;

    const cola = leer(CLAVE_PENDIENTES, []);

    eventos.forEach((evento) => cola.push({ sesionId, evento }));
    escribir(CLAVE_PENDIENTES, cola);
    vaciarPendientes();
}

/**
 * Crea la sesión en el servidor con la configuración `c` (la de configGuardada o la del formulario de Estudio).
 * Devuelve { ok: true, id } o { ok: false, errores: [texto] }. Si no hay red lanza el error de fetch.
 */
export async function crearSesionEnServidor(c) {
    const respuesta = await pedirSeguro(urlSesiones(), {
        method: 'POST',
        body: JSON.stringify({
            tarea_id: c.tarea_id || null, contexto_id: c.contexto_id || null, tema: c.tema || null,
            estilo: c.estilo, foco_seg: c.foco, descanso_seg: c.descanso,
            descanso_largo_seg: c.largo, pomodoros_antes_largo: c.ciclos,
        }),
    });
    const datos = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
        return { ok: false, errores: datos.errors ? Object.values(datos.errors).flat() : ['No se pudo iniciar la sesión. Probá de nuevo.'] };
    }

    return { ok: true, id: datos.id };
}

export async function finalizarEnServidor(sesionId) {
    await vaciarPendientes({ forzar: true });

    try {
        await pedirSeguro(`${urlSesiones()}/${sesionId}/finalizar`, { method: 'PATCH' });
    } catch {
        // Si falla, la sesión queda "en curso" y se cierra sola al iniciar la próxima.
    }
}

/* ---------- Estado y suscriptores ---------- */
const recargar = () => { estado = migrarEstado(leer(CLAVE_ESTADO, null)); };
const guardar = () => escribir(CLAVE_ESTADO, estado);

/** Estado guardado tal cual (sin recalcular fases). */
export function estadoGuardado() {
    return estado;
}

/**
 * Se llama cada vez que cambia algo y en cada tic. Recibe { estado, ahora }, donde estado ya viene
 * con las fases vencidas cerradas (en las pestañas sin candado se calcula solo para mostrar).
 * Devuelve una función para cancelar la suscripción.
 */
export function suscribir(fn) {
    suscriptores.push(fn);
    fn(instantanea(Date.now()));

    return () => { suscriptores = suscriptores.filter((f) => f !== fn); };
}

const instantanea = (ahora) => ({ estado: estado ? avanzar(estado, ahora).estado : null, ahora });

function notificar(ahora = Date.now()) {
    const foto = instantanea(ahora);

    suscriptores.forEach((fn) => fn(foto));

    if (foto.estado) {
        const d = describir(foto.estado, ahora);

        document.title = `${d.texto} · ${d.nombre} · FocoDiario`;
    } else {
        document.title = tituloOriginal;
    }
}

function descartarEstado() {
    estado = null;
    escribir(CLAVE_ESTADO, null);
    notificar();
}

export { descartarEstado as descartar };

export function iniciarEstado(sesionId, config, desde = 'foco') {
    estado = crearEstado(sesionId, config, Date.now(), desde);
    guardar();
    notificar();
}

/* ---------- Acciones ---------- */
function transicion(fn) {
    recargar();

    if (!estado) return;

    estado = fn(estado, Date.now());
    guardar();
    notificar();
}

function transicionConEventos(fn) {
    recargar();

    if (!estado) return;

    const resultado = fn(estado, Date.now());

    estado = resultado.estado;
    guardar();
    encolar(estado.sesionId, resultado.eventos);
    notificar();
}

export const acciones = {
    pausar: () => transicion(pausar),
    reanudar: () => transicion(reanudar),
    reiniciar: () => transicion(reiniciar),
    saltar: () => transicionConEventos(saltar),
    siguienteFoco: () => transicionConEventos(iniciarSiguienteFoco),
};

/** Pide confirmación y termina la sesión. Devuelve true si se terminó. */
export async function terminarSesion() {
    recargar();

    if (!estado) return false;

    if (!await confirmar('¿Terminar la sesión? Lo que está en curso se registra tal como está.', { aceptar: 'Terminar' })) return false;

    const sesionId = estado.sesionId;
    const resultado = terminar(estado, Date.now());

    estado = null;
    escribir(CLAVE_ESTADO, null);
    notificar();
    encolar(sesionId, resultado.eventos);
    await finalizarEnServidor(sesionId);

    return true;
}

/* ---------- Ciclo de vida ---------- */
function tic() {
    const ahora = Date.now();

    if (esLider(ahora)) {
        // Se relee por si otra pestaña cambió algo y el evento `storage` todavía no llegó.
        recargar();

        if (estado) {
            const resultado = avanzar(estado, ahora);

            if (resultado.eventos.length > 0) {
                estado = resultado.estado;
                guardar();
                encolar(estado.sesionId, resultado.eventos);
                avisarPorEventos(resultado.eventos);
            }
        }

        if (ahora % 30_000 < 300 && leer(CLAVE_PENDIENTES, []).length > 0) vaciarPendientes();
    }

    notificar(ahora);
}

/**
 * Concilia el estado guardado con lo que el servidor dice al abrir una pantalla:
 *  - si el estado local es de una sesión que ya no está en curso, se descarta;
 *  - si el servidor tiene una sesión abierta sin estado local (otro navegador o datos borrados), se cierra.
 */
export function conciliarSesion(sesionActivaServidor) {
    const guardado = estadoGuardado();

    if (guardado && guardado.sesionId !== sesionActivaServidor) {
        descartarEstado();
    } else if (!guardado && sesionActivaServidor) {
        finalizarEnServidor(sesionActivaServidor);
    }
}

/** Arranca el motor una sola vez, sin importar cuántos módulos lo pidan. */
export function iniciarMotor() {
    if (iniciado) return;

    iniciado = true;
    tituloOriginal = document.title;
    recargar();

    temporizador = setInterval(tic, 250);

    // Al volver a la pestaña se recalcula al instante, sin esperar el siguiente tic.
    document.addEventListener('visibilitychange', tic);
    window.addEventListener('focus', tic);
    window.addEventListener('pagehide', () => {
        try { liberarCandado(window.localStorage, CLAVE_CANDADO, ID_PESTANA); } catch { /* sin almacenamiento */ }
    });

    // Otra pestaña cambió el estado: se adopta y se redibuja.
    window.addEventListener('storage', (evento) => {
        if (evento.key === CLAVE_ESTADO || evento.key === null) {
            recargar();
            notificar();
        }
    });

    esLider();
    vaciarPendientes();
    tic();
}

export const detenerMotor = () => clearInterval(temporizador);
