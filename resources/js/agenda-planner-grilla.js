/*
 * Planner semanal: las tarjetas (los siete días y las cajas Notas y Pendiente) se mueven y se redimensionan
 * en una cuadrícula de 12 columnas (GridStack), igual que las cajas de la hoja del día (agenda-dia.js).
 * La disposición es propia de cada semana (la URL de guardado lleva el lunes), así un guardado pendiente nunca cae en otra. Se carga solo en esa pantalla.
 * En pantallas angostas no hay cuadrícula: las tarjetas se apilan en el orden de la semana.
 */
import { GridStack } from 'gridstack';
import 'gridstack/dist/gridstack.min.css';
import './agenda.js';
import { guardador } from './agenda-contexto.js';
import { moverLayout, redimensionarLayout, ajustarLayout } from './agenda-logica.js';

const contenedor = document.querySelector('[data-plan-grilla]');

if (contenedor) {
    iniciar(contenedor);
}

function iniciar(contenedor) {
    const anuncio = document.getElementById('agenda-anuncio');
    const pantallaAngosta = window.matchMedia('(max-width: 767.98px)');
    const menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
    const claveLayout = `PATCH ${contenedor.dataset.urlLayout}`;

    let grilla = null;
    let listo = false;
    let seArrastro = false; // evita que soltar una tarjeta sobre el título abra la hoja del día

    const anunciar = (texto) => {
        anuncio.textContent = '';
        requestAnimationFrame(() => { anuncio.textContent = texto; });
    };

    const items = () => [...contenedor.querySelectorAll(':scope > .grid-stack-item')];
    const itemDe = (elemento) => elemento.closest('.grid-stack-item');
    const claveDe = (item) => item.getAttribute('gs-id');

    function layoutDe(item) {
        const nodo = item.gridstackNode;

        if (nodo) {
            return { x: nodo.x, y: nodo.y, ancho: nodo.w, alto: nodo.h };
        }

        return {
            x: Number(item.getAttribute('gs-x') ?? 0),
            y: Number(item.getAttribute('gs-y') ?? 0),
            ancho: Number(item.getAttribute('gs-w') ?? 4),
            alto: Number(item.getAttribute('gs-h') ?? 8),
        };
    }

    function guardarLayout() {
        guardador.programar(claveLayout, { tarjetas: items().map((item) => ({ clave: claveDe(item), ...layoutDe(item) })) });
    }

    function describir(item) {
        const { x, y, ancho, alto } = layoutDe(item);
        const nombre = item.querySelector('.plan-dia-titulo, .plan-nota-titulo')?.textContent.replace(/\s+/g, ' ').trim() ?? 'tarjeta';

        return `Tarjeta ${nombre}: columna ${x + 1}, fila ${y + 1}, ${ancho} de ancho, ${alto} de alto.`;
    }

    /* ---------- Cuadrícula (pantallas anchas) ---------- */

    function iniciarGrilla() {
        contenedor.classList.add('grid-stack');
        grilla = GridStack.init({
            column: 12,
            cellHeight: 24,
            margin: 8,
            float: true,
            animate: !menosMovimiento.matches,
            disableOneColumnMode: true,
            alwaysShowResizeHandle: true,
            draggable: { handle: '.plan-dia-cab, .plan-nota-cab' },
            resizable: { handles: 'e, se, s' },
        }, contenedor);

        grilla.on('change', () => {
            if (listo) {
                guardarLayout();
            }
        });
        grilla.on('dragstart', () => { seArrastro = true; });
        grilla.on('dragstop', () => { setTimeout(() => { seArrastro = false; }, 0); });

        contenedor.classList.remove('es-movil');
        listo = true;
    }

    function apagarGrilla() {
        if (grilla) {
            grilla.destroy(false);
            grilla = null;
        }

        listo = false;
        contenedor.className = contenedor.className.replace(/\bgrid-stack\S*|\bgs-\d+/g, '').trim();
        contenedor.removeAttribute('style');
        contenedor.removeAttribute('gs-current-row');
        contenedor.classList.add('es-movil');
    }

    function aplicarModo() {
        if (pantallaAngosta.matches) {
            apagarGrilla();
        } else if (!grilla) {
            iniciarGrilla();
        }
    }

    pantallaAngosta.addEventListener('change', aplicarModo);

    // Soltar una tarjeta arrastrada desde el enlace del día no debe navegar.
    contenedor.addEventListener('click', (evento) => {
        if (seArrastro && evento.target.closest('.plan-dia-enlace')) {
            evento.preventDefault();
        }
    }, true);

    /* ---------- Mover y cambiar tamaño con el teclado ---------- */

    function cambiarLayout(item, cambios) {
        const nuevo = ajustarLayout({ ...layoutDe(item), ...cambios });

        if (grilla) {
            grilla.update(item, { x: nuevo.x, y: nuevo.y, w: nuevo.ancho, h: nuevo.alto });
            guardarLayout();
        }

        anunciar(describir(item));
    }

    contenedor.addEventListener('keydown', (evento) => {
        if (!evento.target.matches('[data-agarre]')) {
            return;
        }

        const flecha = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[evento.key];

        if (!flecha || !grilla) {
            return;
        }

        evento.preventDefault();

        const item = itemDe(evento.target);

        if (evento.shiftKey) {
            cambiarLayout(item, redimensionarLayout(layoutDe(item), flecha[0], flecha[1]));
        } else {
            cambiarLayout(item, moverLayout(layoutDe(item), flecha[0], flecha[1]));
        }

        // GridStack rehace el elemento al moverlo; el foco se conserva en el agarre.
        item.querySelector('[data-agarre]')?.focus();
    });

    aplicarModo();
}
