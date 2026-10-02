/*
 * Toasts de recordatorios. Cada minuto (y al volver a la pestaña) se pregunta al servidor qué recordatorios
 * ya llegaron a su hora; cada uno aparece como un aviso que no se va solo, con:
 *  - "Listo": lo marca como avisado;
 *  - "10 min más": lo pospone (la hora nueva la calcula el servidor);
 *  - la ×: lo cierra sin marcarlo (sigue pendiente en Hoy y en Tareas, pero no vuelve a saltar a esa hora).
 * Todas las pestañas lo muestran; al resolverlo o cerrarlo en una, desaparece de las demás (localStorage).
 * Si la pestaña está oculta y hay permiso (lo pide Estudio), también sale una notificación del sistema.
 */
import { aviso } from './avisos.js';
import { agregarCerrado, claveDe, detalleDe, planificar } from './recordatorios-avisos-logica.js';
import { pedirSeguro } from './red.js';

const CADA = 60_000;
const CLAVE_CERRADOS = 'focodiario.recordatorios.cerrados';
const CLAVE_NOTIFICADOS = 'focodiario.recordatorios.notificados';

const abiertos = new Map(); // clave -> { quitar }
let vencidos = [];
let consultando = false;

function leer(clave) {
    try {
        const valor = JSON.parse(window.localStorage.getItem(clave));

        return Array.isArray(valor) ? valor : [];
    } catch {
        return [];
    }
}

function guardar(clave, valor) {
    try {
        window.localStorage.setItem(clave, JSON.stringify(valor));
    } catch {
        // Sin almacenamiento: los toasts funcionan igual, solo que cada pestaña por su cuenta.
    }
}

function cerrar(recordatorio) {
    guardar(CLAVE_CERRADOS, agregarCerrado(leer(CLAVE_CERRADOS), claveDe(recordatorio)));
    abiertos.delete(claveDe(recordatorio));
}

function reabrir(recordatorio) {
    guardar(CLAVE_CERRADOS, leer(CLAVE_CERRADOS).filter((clave) => clave !== claveDe(recordatorio)));
}

/** Manda "Listo" o "10 min más". Si falla, el aviso vuelve en la próxima consulta. */
async function resolver(recordatorio, url, textoError) {
    cerrar(recordatorio);

    try {
        const respuesta = await pedirSeguro(url, { method: 'PATCH', body: '{}' });

        if (!respuesta.ok) throw new Error(String(respuesta.status));

        vencidos = vencidos.filter((otro) => otro.id !== recordatorio.id);
    } catch {
        reabrir(recordatorio);
        aviso.error(textoError);
    }
}

function notificarAlSistema(recordatorio) {
    if (!document.hidden || !('Notification' in window) || Notification.permission !== 'granted') return;

    const clave = claveDe(recordatorio);
    const notificados = leer(CLAVE_NOTIFICADOS);

    if (notificados.includes(clave)) return;

    guardar(CLAVE_NOTIFICADOS, agregarCerrado(notificados, clave));

    try {
        new Notification(`Recordatorio · ${recordatorio.hora}`, { body: recordatorio.mensaje, tag: `recordatorio-${recordatorio.id}` });
    } catch {
        // Algunos navegadores solo permiten notificar desde un service worker.
    }
}

function mostrar(recordatorio) {
    const toast = aviso.recordatorio(recordatorio.mensaje, {
        detalle: detalleDe(recordatorio),
        acciones: [
            { texto: 'Listo', alHacer: () => resolver(recordatorio, recordatorio.url_avisar, 'No se pudo marcar el recordatorio. Va a volver a aparecer.') },
            { texto: '10 min más', alHacer: () => resolver(recordatorio, recordatorio.url_posponer, 'No se pudo posponer el recordatorio. Va a volver a aparecer.') },
        ],
        alRetirar: () => cerrar(recordatorio),
    });

    abiertos.set(claveDe(recordatorio), toast);
    notificarAlSistema(recordatorio);
}

/** Abre y quita toasts según lo último del servidor y lo cerrado en cualquier pestaña. */
function sincronizar() {
    const { mostrar: nuevos, quitar } = planificar(vencidos, leer(CLAVE_CERRADOS), new Set(abiertos.keys()));

    quitar.forEach((clave) => {
        abiertos.get(clave)?.quitar();
        abiertos.delete(clave);
    });
    nuevos.forEach(mostrar);
}

async function consultar(url) {
    if (consultando) return;

    consultando = true;

    try {
        const respuesta = await pedirSeguro(url);

        if (!respuesta.ok) return;

        vencidos = (await respuesta.json()).recordatorios ?? [];
        sincronizar();
    } catch {
        // Sin red: se reintenta en la próxima vuelta.
    } finally {
        consultando = false;
    }
}

export function activarAvisosDeRecordatorios() {
    const url = document.querySelector('meta[name="url-recordatorios-vencidos"]')?.content;

    if (!url) return;

    consultar(url);
    setInterval(() => consultar(url), CADA);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) consultar(url);
    });

    // Cerrado o resuelto en otra pestaña: se quita también acá.
    window.addEventListener('storage', (evento) => {
        if (evento.key === CLAVE_CERRADOS) sincronizar();
    });
}
