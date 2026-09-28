import 'bootstrap';
import htmx from 'htmx.org';

window.htmx = htmx;

// Laravel exige el token CSRF en cada petición que modifica datos.
document.addEventListener('htmx:configRequest', (evento) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (token) {
        evento.detail.headers['X-CSRF-TOKEN'] = token;
    }
});
