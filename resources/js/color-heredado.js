/*
 * Pista de color de los diálogos y formularios de nota y tarea: con "Sin color" elegido, muestra de qué contexto
 * hereda el color. Se actualiza al cambiar el contexto o el color, y al abrir un diálogo (cuyos valores se cargan por JS).
 * Cada opción del selector de contexto trae su color efectivo en data-color-heredado y data-color-origen.
 */
import { pistaDeColor } from './color-heredado-logica.js';

const SELECTOR_SELECT = 'select[name="contexto_id"]';

function actualizar(formulario) {
    const pista = formulario.querySelector('[data-color-pista]');
    const select = formulario.querySelector(SELECTOR_SELECT);

    if (!pista || !select) return;

    const opcion = select.selectedOptions[0];
    const resultado = pistaDeColor({
        colorPropio: formulario.querySelector('input[name="color"]:checked')?.value ?? '',
        clave: opcion?.dataset.colorHeredado ?? '',
        origen: opcion?.dataset.colorOrigen ?? '',
    });

    pista.hidden = resultado === null;

    if (resultado === null) return;

    pista.querySelector('[data-color-pista-texto]').textContent = resultado.texto;
    pista.querySelector('.color-pista-muestra').style.cssText = `--pista-fondo: ${resultado.fondo}; --pista-marca: ${resultado.marca}`;
}

const formularioDe = (nodo) => nodo.closest?.('form');

document.addEventListener('change', (evento) => {
    const formulario = formularioDe(evento.target);

    if (formulario?.querySelector('[data-color-pista]') && evento.target.matches(`${SELECTOR_SELECT}, input[name="color"]`)) {
        actualizar(formulario);
    }
});

// Los diálogos cargan sus valores por JS antes de abrirse: al abrir se vuelve a calcular.
const observador = new MutationObserver((cambios) => {
    cambios.forEach(({ target }) => {
        if (target.open) target.querySelectorAll('form').forEach((formulario) => formulario.querySelector('[data-color-pista]') && actualizar(formulario));
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('dialog').forEach((dialogo) => {
        if (dialogo.querySelector('[data-color-pista]')) observador.observe(dialogo, { attributes: true, attributeFilter: ['open'] });
    });
});
