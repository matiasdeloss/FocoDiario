/*
 * Hoja del día: lienzo en cuadrícula (GridStack, 12 columnas) donde se crean, mueven y redimensionan cajas.
 * Se carga solo en esa pantalla (entrada aparte de Vite). En pantallas angostas no hay cuadrícula: las cajas
 * se apilan en una columna, en orden de lectura, y se reordenan con los botones Subir y Bajar.
 */
import { GridStack } from 'gridstack';
import 'gridstack/dist/gridstack.min.css';
import './agenda.js';
import { editor, guardador, mostrarEstado } from './agenda-contexto.js';
import { pedirJson } from './agenda-red.js';
import {
    ajustarLayout, errorDeHoras, moverLayout, ordenarPorPosicion, redimensionarLayout, reordenar,
} from './agenda-logica.js';
import { confirmar } from './confirmar.js';

const lienzo = document.querySelector('[data-lienzo]');

if (lienzo) {
    iniciar(lienzo);
}

function iniciar(lienzo) {
    const contenedor = lienzo.querySelector('[data-cajas]');
    const dialogo = document.getElementById('dialogo-caja');
    const anuncio = document.getElementById('agenda-anuncio');
    const vacio = document.querySelector('[data-vacio]');
    const pantallaAngosta = window.matchMedia('(max-width: 767.98px)');
    const menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
    const claveLayout = `PATCH ${lienzo.dataset.urlLayout}`;

    let grilla = null;
    let listo = false;
    let actual = null; // controlador de la caja abierta en el menú

    const anunciar = (texto) => {
        anuncio.textContent = '';
        // Un cuadro de texto vacío antes del nuevo mensaje hace que el lector repita mensajes iguales.
        requestAnimationFrame(() => { anuncio.textContent = texto; });
    };

    /* ---------- Lectura de la posición de cada caja ---------- */

    const itemDe = (articulo) => articulo.closest('.grid-stack-item');
    const items = () => [...contenedor.querySelectorAll(':scope > .grid-stack-item')];

    function layoutDe(item) {
        const nodo = item.gridstackNode;

        if (nodo) {
            return { x: nodo.x, y: nodo.y, ancho: nodo.w, alto: nodo.h };
        }

        return {
            x: Number(item.getAttribute('gs-x') ?? 0),
            y: Number(item.getAttribute('gs-y') ?? 0),
            ancho: Number(item.getAttribute('gs-w') ?? 6),
            alto: Number(item.getAttribute('gs-h') ?? 8),
        };
    }

    const idDe = (item) => Number(item.getAttribute('gs-id'));
    const todasLasCajas = () => items().map((item) => ({ id: idDe(item), ...layoutDe(item) }));

    function guardarLayout() {
        guardador.programar(claveLayout, { cajas: todasLasCajas() });
    }

    function describir(item) {
        const { x, y, ancho, alto } = layoutDe(item);
        const titulo = item.querySelector('[data-campo="titulo"]')?.value.trim() || 'sin título';

        return `Caja ${titulo}: columna ${x + 1}, fila ${y + 1}, ${ancho} de ancho, ${alto} de alto.`;
    }

    /* ---------- Cuadrícula (pantallas anchas) ---------- */

    function iniciarGrilla() {
        contenedor.classList.add('grid-stack');
        grilla = GridStack.init({
            column: 12,
            cellHeight: 24,
            margin: 4,
            float: true,
            minRow: 30,
            animate: !menosMovimiento.matches,
            disableOneColumnMode: true,
            alwaysShowResizeHandle: true,
            draggable: { handle: '.caja-cab' },
            resizable: { handles: 'e, se, s' },
        }, contenedor);

        grilla.on('change', () => {
            if (listo) {
                guardarLayout();
            }
        });

        lienzo.classList.remove('es-movil');
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
        lienzo.classList.add('es-movil');
        ordenarDom();
    }

    /** En una columna, el orden en pantalla es el orden de lectura de la hoja. */
    function ordenarDom() {
        const porId = new Map(items().map((item) => [idDe(item), item]));

        ordenarPorPosicion(todasLasCajas()).forEach((caja) => contenedor.append(porId.get(caja.id)));
    }

    function aplicarModo() {
        dialogo.classList.toggle('es-movil', pantallaAngosta.matches);

        if (pantallaAngosta.matches) {
            apagarGrilla();
        } else if (!grilla) {
            iniciarGrilla();
        }
    }

    pantallaAngosta.addEventListener('change', aplicarModo);

    /* ---------- Mover y cambiar tamaño sin arrastrar ---------- */

    /** Cambia posición o tamaño de una caja por teclado o desde el menú. */
    function cambiarLayout(item, cambios) {
        const nuevo = ajustarLayout({ ...layoutDe(item), ...cambios });

        if (grilla) {
            grilla.update(item, { x: nuevo.x, y: nuevo.y, w: nuevo.ancho, h: nuevo.alto });
            // GridStack avisa "change" solo, pero en cajas sin movimiento real conviene guardar igual.
            guardarLayout();
        }

        anunciar(describir(item));
    }

    /** En una columna: sube o baja la caja en el orden de lectura. */
    function reordenarCaja(item, direccion) {
        const cambios = reordenar(todasLasCajas(), idDe(item), direccion);

        if (cambios.length === 0) {
            anunciar(direccion < 0 ? 'La caja ya es la primera.' : 'La caja ya es la última.');

            return;
        }

        cambios.forEach((cambio) => {
            const otro = items().find((elemento) => idDe(elemento) === cambio.id);

            otro.setAttribute('gs-x', cambio.x);
            otro.setAttribute('gs-y', cambio.y);
        });

        ordenarDom();
        guardarLayout();

        const posicion = items().indexOf(item) + 1;

        anunciar(`Caja ${item.querySelector('[data-campo="titulo"]')?.value.trim() || 'sin título'}: lugar ${posicion} de ${items().length}.`);
        item.querySelector('[data-agarre]')?.focus();
    }

    function moverConTeclado(evento) {
        const teclas = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
        const flecha = teclas[evento.key];

        if (!flecha) {
            return;
        }

        evento.preventDefault();

        const item = itemDe(evento.target);

        if (pantallaAngosta.matches) {
            reordenarCaja(item, flecha[1]);
        } else if (evento.shiftKey) {
            cambiarLayout(item, redimensionarLayout(layoutDe(item), flecha[0], flecha[1]));
        } else {
            cambiarLayout(item, moverLayout(layoutDe(item), flecha[0], flecha[1]));
        }

        // GridStack rehace el elemento al moverlo; el foco se conserva en el agarre.
        item.querySelector('[data-agarre]')?.focus();
    }

    contenedor.addEventListener('keydown', (evento) => {
        if (evento.target.matches('[data-agarre]')) {
            moverConTeclado(evento);
        }
    });

    /* ---------- Menú de opciones de la caja ---------- */

    const campo = (nombre) => dialogo.querySelector(`[data-dc="${nombre}"]`);

    function abrirMenu(controlador) {
        actual = controlador;

        const { estado } = controlador;

        campo('titulo-caja').textContent = estado.titulo?.trim() || 'Sin título';
        campo('contexto').value = String(estado.contexto_id ?? '');
        dialogo.querySelectorAll('[name="dc-tipo"]').forEach((radio) => {
            radio.checked = radio.value === estado.tipo;
        });
        dialogo.querySelectorAll('[name="dc-borde-grosor"]').forEach((radio) => {
            radio.checked = radio.value === String(estado.borde_grosor ?? 1);
        });
        dialogo.querySelectorAll('[name="dc-borde-color"]').forEach((radio) => {
            radio.checked = radio.value === (estado.borde_color ?? '');
        });
        campo('hora_inicio').value = estado.hora_inicio ?? '';
        campo('hora_fin').value = estado.hora_fin ?? '';
        campo('hecha').checked = Boolean(estado.hecha);
        campo('error-horas').textContent = '';

        dialogo.showModal();
    }

    contenedor.addEventListener('caja:menu', (evento) => {
        abrirMenu(editor.obtener(evento.target.closest('[data-caja]')));
    });

    dialogo.addEventListener('close', () => {
        const articulo = actual?.articulo;

        actual = null;
        // El foco vuelve al botón de opciones de la caja.
        articulo?.querySelector('[data-accion="menu"]')?.focus();
    });

    dialogo.addEventListener('change', (evento) => {
        if (!actual) {
            return;
        }

        const objetivo = evento.target;

        if (objetivo.name === 'dc-contexto') {
            actual.aplicar({ contexto_id: objetivo.value === '' ? null : Number(objetivo.value) });
        } else if (objetivo.name === 'dc-borde-grosor') {
            actual.aplicar({ borde_grosor: Number(objetivo.value) });
        } else if (objetivo.name === 'dc-borde-color') {
            actual.aplicar({ borde_color: objetivo.value === '' ? null : objetivo.value });
        } else if (objetivo.name === 'dc-tipo') {
            actual.cambiarTipo(objetivo.value);
        } else if (objetivo.matches('[data-dc="hecha"]')) {
            actual.aplicar({ hecha: objetivo.checked });
        } else if (objetivo.matches('[data-dc="hora_inicio"], [data-dc="hora_fin"]')) {
            const inicio = campo('hora_inicio').value || null;
            const fin = campo('hora_fin').value || null;
            const error = errorDeHoras(inicio, fin);

            campo('error-horas').textContent = error ?? '';

            if (!error) {
                actual.aplicar({ hora_inicio: inicio, hora_fin: fin });
            }
        }
    });

    dialogo.addEventListener('click', async (evento) => {
        const boton = evento.target.closest('button[data-orden], button[data-eliminar]');

        if (!boton || !actual) {
            return;
        }

        const item = itemDe(actual.articulo);

        if (boton.dataset.orden) {
            reordenarCaja(item, Number(boton.dataset.orden));
        } else if (boton.dataset.eliminar !== undefined) {
            const titulo = actual.estado.titulo?.trim() || 'sin título';

            if (await confirmar(`¿Eliminar la caja "${titulo}"? No se puede deshacer.`)) {
                await eliminarCaja(actual);
            }
        }
    });

    async function eliminarCaja(controlador) {
        const item = itemDe(controlador.articulo);
        const url = controlador.articulo.dataset.url;

        try {
            await pedirJson('DELETE', url);
        } catch (error) {
            mostrarEstado('error', error.mensaje ?? 'No se pudo eliminar.');

            return;
        }

        guardador.descartar(controlador.clave);
        actual = null;
        dialogo.close();

        if (grilla) {
            grilla.removeWidget(item);
        } else {
            item.remove();
        }

        actualizarVacio();
        anunciar('Caja eliminada.');
    }

    /* ---------- Nueva caja ---------- */

    function actualizarVacio() {
        if (vacio) {
            vacio.hidden = items().length > 0;
        }
    }

    document.querySelector('[data-nueva-caja]')?.addEventListener('click', async (evento) => {
        const boton = evento.currentTarget;

        boton.disabled = true;

        try {
            const datos = await pedirJson('POST', lienzo.dataset.urlCajas, { fecha: lienzo.dataset.fecha, tipo: 'texto' });
            const plantilla = document.createElement('template');

            plantilla.innerHTML = datos.html.trim();

            const item = plantilla.content.firstElementChild;

            contenedor.append(item);

            if (grilla) {
                grilla.makeWidget(item);
            }

            const controlador = editor.iniciar(item.querySelector('[data-caja]'));

            actualizarVacio();
            item.scrollIntoView({ block: 'center', behavior: menosMovimiento.matches ? 'auto' : 'smooth' });
            controlador.enfocarTitulo();
            anunciar('Caja nueva creada.');
        } catch (error) {
            mostrarEstado('error', error.mensaje ?? 'No se pudo crear la caja.');
        } finally {
            boton.disabled = false;
        }
    });

    /* ---------- Mostrar u ocultar lo hecho ---------- */

    const PREFERENCIAS = { hechas: 'agenda.mostrarHechas', tildadas: 'agenda.mostrarTildadas' };

    function leerPreferencia(clave) {
        try {
            return window.localStorage.getItem(clave) !== '0';
        } catch {
            return true;
        }
    }

    function guardarPreferencia(clave, valor) {
        try {
            window.localStorage.setItem(clave, valor ? '1' : '0');
        } catch {
            // Sin almacenamiento: la preferencia dura hasta recargar.
        }
    }

    document.querySelectorAll('[data-interruptor]').forEach((interruptor) => {
        const tipo = interruptor.dataset.interruptor;
        const clase = tipo === 'hechas' ? 'ocultar-hechas' : 'ocultar-tildadas';
        const aplicar = (mostrar) => {
            lienzo.classList.toggle(clase, !mostrar);
            interruptor.checked = mostrar;
        };

        aplicar(leerPreferencia(PREFERENCIAS[tipo]));

        interruptor.addEventListener('change', () => {
            aplicar(interruptor.checked);
            guardarPreferencia(PREFERENCIAS[tipo], interruptor.checked);
            anunciar(interruptor.checked ? `Mostrando ${tipo}.` : `Ocultando ${tipo}.`);
        });
    });

    /* ---------- Arranque ---------- */

    editor.iniciarTodas(contenedor);
    aplicarModo();
    actualizarVacio();
}
