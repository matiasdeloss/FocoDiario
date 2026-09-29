/*
 * Entrada suave al hacer scroll: los bloques principales aparecen con opacidad y un desplazamiento
 * de 12px. Solo anima transform y opacity, usa IntersectionObserver y respeta "menos movimiento".
 * Los elementos parten visibles: la clase de estado inicial la agrega este módulo solo si hay
 * IntersectionObserver, y solo a lo que todavía está fuera de la pantalla (así no hay parpadeo al cargar).
 * No toca el calendario, el tablero, el Pomodoro ni lo que HTMX inyecte después de la carga.
 */
const OBJETIVOS = '.tarjeta, .hoy-tarjeta, .recomendacion, .nota-item, .ficha-metodo';
const EXCLUIDOS = '[data-calendario], #tablero, #pomodoro, #hoy-pomodoro, #pomodoro-widget, [data-hoy-pomodoro]';
const MAXIMO = 40;
const TOPE_ESCALON = 5;

function iniciar() {
    const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reducido || !('IntersectionObserver' in window)) return;

    const alto = window.innerHeight;
    const candidatos = [...document.querySelectorAll(`main ${OBJETIVOS.split(', ').join(', main ')}`)]
        .filter((el) => !el.closest(EXCLUIDOS)
            && !el.parentElement.closest(OBJETIVOS)
            && el.getBoundingClientRect().top > alto)
        .slice(0, MAXIMO);

    if (candidatos.length === 0) return;

    const observador = new IntersectionObserver((entradas) => {
        entradas.forEach((entrada) => {
            if (!entrada.isIntersecting) return;

            const el = entrada.target;

            observador.unobserve(el);
            el.addEventListener('transitionend', function limpiar(evento) {
                if (evento.target !== el) return;

                el.removeEventListener('transitionend', limpiar);
                el.classList.remove('entrada-pendiente', 'entrada-lista');
            });
            el.classList.add('entrada-lista');
        });
    }, { threshold: 0.08 });

    const contadores = new Map();

    candidatos.forEach((el) => {
        const cuenta = contadores.get(el.parentElement) ?? 0;

        contadores.set(el.parentElement, cuenta + 1);
        el.style.setProperty('--indice', Math.min(cuenta, TOPE_ESCALON));
        el.classList.add('entrada-pendiente');
        observador.observe(el);
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
else iniciar();
