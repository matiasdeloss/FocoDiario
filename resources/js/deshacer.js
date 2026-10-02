/*
 * Borrar con "Deshacer" en lugar de preguntar antes. Los formularios HTMX con data-deshacer="Tarea eliminada."
 * ocultan su elemento al instante y muestran un aviso con Deshacer; el pedido DELETE sale recién cuando el aviso
 * se retira. Si la página se cierra antes, los borrados pendientes se mandan igual (keepalive).
 */
import { aviso } from './avisos.js';
import { pedirSeguro } from './red.js';

/** Borrados esperando que se retire su aviso: url -> función que los manda por HTMX. */
const pendientes = new Map();

function mandarPendientesAlSalir() {
    pendientes.forEach((_, url) => {
        pedirSeguro(url, { method: 'DELETE', keepalive: true, headers: { 'HX-Request': 'true' } }).catch(() => {});
    });
    pendientes.clear();
}

export function activarDeshacer() {
    // En captura: decide antes que la confirmación general de app.js (que así no pregunta nada).
    document.addEventListener('htmx:confirm', (evento) => {
        const origen = evento.detail.elt;
        const texto = origen?.dataset?.deshacer;
        const url = origen?.getAttribute('hx-delete');

        if (!texto || !url) return;

        evento.preventDefault();
        evento.stopImmediatePropagation();

        const objetivo = evento.detail.target;

        const enviar = () => {
            if (!pendientes.delete(url)) return;

            evento.detail.issueRequest(true);
        };

        objetivo.classList.add('es-eliminando');
        pendientes.set(url, enviar);

        const { botonAccion } = aviso.info(texto, {
            accion: {
                texto: 'Deshacer',
                alHacer: () => {
                    pendientes.delete(url);
                    objetivo.classList.remove('es-eliminando');
                    origen.querySelector('[type="submit"]')?.focus({ preventScroll: true });
                },
            },
            alRetirar: enviar,
        });

        // El botón que tenía el foco quedó oculto: el foco pasa a Deshacer.
        botonAccion?.focus({ preventScroll: true });
    }, true);

    window.addEventListener('pagehide', mandarPendientesAlSalir);
}
