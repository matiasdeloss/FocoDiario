import 'bootstrap';
import htmx from 'htmx.org';
import './pomodoro.js';
import './dialogos-tablero.js';
import './pomodoro-widget.js';
import './entrada-scroll.js';
import './tema.js';
import './color-heredado.js';
import { aviso, escucharAvisosDelServidor, prepararAvisosFlash } from './avisos.js';
import { activarFormulariosConConfirmacion, confirmar } from './confirmar.js';
import { activarCuenta } from './cuenta.js';
import { activarDeshacer } from './deshacer.js';
import { activarAvisosDeRecordatorios } from './recordatorios-avisos.js';
import { irAEntrar, renovarToken } from './red.js';

window.htmx = htmx;

// La CSP no permite eval y la app no usa hx-on ni valores "js:": se apaga explícitamente.
htmx.config.allowEval = false;

escucharAvisosDelServidor();
prepararAvisosFlash();
activarFormulariosConConfirmacion();
activarDeshacer();
activarAvisosDeRecordatorios();
activarCuenta();

// Selects y fechas que filtran: se envían al cambiar (reemplaza a los onchange="this.form.submit()").
document.addEventListener('change', (evento) => {
    if (evento.target.matches?.('[data-envia-al-cambiar]')) evento.target.form?.requestSubmit();
});

// hx-confirm usa la misma ventana de confirmación que el resto de la app.
document.addEventListener('htmx:confirm', async (evento) => {
    if (!evento.detail.question) return;

    evento.preventDefault();

    if (await confirmar(evento.detail.question)) evento.detail.issueRequest(true);
});

// Deja ver el atenuado de la fila (htmx-swapping) antes de reemplazarla.
// Con "menos movimiento" no se espera nada.
htmx.config.defaultSwapDelay = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 120;

// Laravel exige el token CSRF en cada petición que modifica datos.
document.addEventListener('htmx:configRequest', (evento) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (token) {
        evento.detail.headers['X-CSRF-TOKEN'] = token;
    }
});

// HTMX no reemplaza nada ante un error: sin esto, marcar, borrar o fijar podía fallar en silencio.
document.addEventListener('htmx:responseError', async (evento) => {
    const estado = evento.detail.xhr.status;

    if (estado === 401) {
        irAEntrar();

        return;
    }

    if (estado === 419) {
        // La página quedó abierta más que la sesión: se renueva el token y el próximo intento ya funciona.
        const renovado = await renovarToken();

        if (renovado) {
            aviso.aviso('La sesión se había vencido y ya se renovó. Volvé a intentarlo.');
        } else {
            aviso.error('La sesión venció. Recargá la página para seguir.');
        }

        return;
    }

    aviso.error(estado >= 500
        ? 'Hubo un error en el servidor y no se guardó el cambio. Probá de nuevo en un rato.'
        : 'No se pudo completar la acción. Recargá la página y probá de nuevo.');
});

document.addEventListener('htmx:sendError', () => {
    aviso.error('No hay conexión con el servidor. El cambio no se guardó.');
});

// El calendario (FullCalendar) solo se descarga en su página.
if (document.querySelector('[data-calendario]')) {
    import('./calendario.js');
}
