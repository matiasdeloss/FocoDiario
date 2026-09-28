import 'bootstrap';
import htmx from 'htmx.org';
import './pomodoro.js';
import './tablero.js';
import './pomodoro-widget.js';

window.htmx = htmx;

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

// El calendario (FullCalendar) solo se descarga en su página.
if (document.querySelector('[data-calendario]')) {
    import('./calendario.js');
}
