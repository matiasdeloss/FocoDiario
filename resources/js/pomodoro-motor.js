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
import {
    avanzar, crearEstado, DESCANSO, describir, FOCO, iniciarSiguienteFoco, pausar, reanudar, reiniciar, saltar, terminar,
} from './pomodoro-logica.js';

export const CLAVE_CONFIG = 'focodiario.estudio.config';
export const CLAVE_ESTADO = 'focodiario.estudio.estado';
export const CLAVE_PENDIENTES = 'focodiario.estudio.pendientes';
export const CLAVE_SONIDO = 'focodiario.estudio.sonido';
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

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

export const encabezados = () => ({
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-CSRF-TOKEN': csrf(),
});

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

function sonar() {
    if (!sonidoActivo()) return;

    try {
        prepararAudio();
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

                const { sesionId, evento } = cola[0];
                const cuerpo = {
                    ...evento,
                    inicio: new Date(evento.inicio).toISOString(),
                    fin: new Date(evento.fin).toISOString(),
                };
                const respuesta = await fetch(`${urlSesiones()}/${sesionId}/intervalos`, {
                    method: 'POST', headers: encabezados(), body: JSON.stringify(cuerpo),
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

export async function finalizarEnServidor(sesionId) {
    await vaciarPendientes({ forzar: true });

    try {
        await fetch(`${urlSesiones()}/${sesionId}/finalizar`, { method: 'PATCH', headers: encabezados() });
    } catch {
        // Si falla, la sesión queda "en curso" y se cierra sola al iniciar la próxima.
    }
}

/* ---------- Estado y suscriptores ---------- */
const recargar = () => { estado = leer(CLAVE_ESTADO, null); };
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

export function iniciarEstado(sesionId, config) {
    estado = crearEstado(sesionId, config, Date.now());
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

    if (!window.confirm('¿Terminar la sesión? Lo que está en curso se registra tal como está.')) return false;

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
