/*
 * Planner semanal: título editable (autoguardado al salir del campo) y "+N más" en las cajas de los días
 * cuando los renglones no entran en el alto de la caja.
 */
import { guardador } from './agenda-contexto.js';

const TITULO_POR_DEFECTO = 'Planner semanal';

const campoTitulo = document.querySelector('[data-titulo-planner]');

if (campoTitulo) {
    const guardar = () => {
        const titulo = campoTitulo.value.trim();

        if (titulo === campoTitulo.dataset.original) {
            return;
        }

        campoTitulo.dataset.original = titulo;
        campoTitulo.value = titulo || TITULO_POR_DEFECTO;
        guardador.programar(`PUT ${campoTitulo.dataset.url}`, { titulo });
        guardador.vaciar();
    };

    campoTitulo.addEventListener('change', guardar);
    campoTitulo.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            campoTitulo.blur();
        }
    });
    campoTitulo.addEventListener('blur', () => {
        if (campoTitulo.value.trim() === '') {
            campoTitulo.value = campoTitulo.dataset.original || TITULO_POR_DEFECTO;
        }
    });
}

/** Oculta los renglones que no caben y muestra el enlace con cuántos quedaron fuera. */
function ajustarDia(lineas) {
    const mas = lineas.parentElement.querySelector('[data-plan-mas]');
    const items = [...lineas.children];

    items.forEach((item) => { item.hidden = false; });

    if (!mas) {
        return;
    }

    mas.hidden = true;

    if (lineas.scrollHeight <= lineas.clientHeight + 1) {
        return;
    }

    // Se reserva el lugar del enlace y se van quitando renglones desde el final hasta que entren.
    mas.hidden = false;
    mas.textContent = '+0 más';

    let ocultos = 0;

    while (ocultos < items.length - 1 && lineas.scrollHeight > lineas.clientHeight + 1) {
        items[items.length - 1 - ocultos].hidden = true;
        ocultos++;
    }

    mas.textContent = `+${ocultos} más`;
    mas.setAttribute('aria-label', `Ver ${ocultos} ${ocultos === 1 ? 'actividad más' : 'actividades más'} del ${mas.dataset.dia}: abrir la hoja del día`);
}

const listas = [...document.querySelectorAll('[data-plan-lineas]')];

if (listas.length > 0) {
    let cuadro = null;
    const ajustarTodas = () => {
        cancelAnimationFrame(cuadro);
        cuadro = requestAnimationFrame(() => listas.forEach(ajustarDia));
    };

    ajustarTodas();
    document.fonts?.ready.then(ajustarTodas);
    new ResizeObserver(ajustarTodas).observe(document.querySelector('.plan-dias'));
}
