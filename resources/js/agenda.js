/*
 * Agenda: parte común del planner semanal y de la hoja del día (entrada aparte de Vite, ver vite.config.js).
 * Aquí: cajas de Notas y Pendiente, salto a una fecha, ventana de actividades. GridStack solo se carga en la hoja del día (agenda-dia.js).
 */
import { editor, guardador } from './agenda-contexto.js';
import { lunesDe } from './agenda-logica.js';
import './agenda-planner.js';

editor.iniciarTodas();

// Cerrar las ventanas (dialog) con su botón y al hacer clic sobre el fondo.
document.addEventListener('click', (evento) => {
    const abrir = evento.target.closest('[data-abrir-dialogo]');

    if (abrir) {
        document.getElementById(abrir.dataset.abrirDialogo)?.showModal();

        return;
    }

    if (evento.target.closest('[data-cerrar-dialogo]')) {
        evento.target.closest('dialog')?.close();

        return;
    }

    if (evento.target instanceof HTMLDialogElement && evento.target.open) {
        evento.target.close();
    }
});

// Un error de validación en una actividad vuelve a abrir la ventana con el mensaje.
document.querySelectorAll('dialog[data-abrir]').forEach((dialogo) => dialogo.showModal());

// Los cambios pendientes se mandan antes de recargar la página con un formulario (actividades).
document.addEventListener('submit', async (evento) => {
    const formulario = evento.target;

    if (evento.defaultPrevented || !(formulario instanceof HTMLFormElement) || !formulario.matches('[data-tras-guardar]') || formulario.dataset.listo) {
        return;
    }

    evento.preventDefault();
    await guardador.vaciar();
    formulario.dataset.listo = '1';
    formulario.requestSubmit(evento.submitter ?? undefined);
});

// Ir a una semana: cualquier fecha lleva al lunes de su semana.
document.querySelector('[data-ir-fecha]')?.addEventListener('change', (evento) => {
    const lunes = lunesDe(evento.target.value);

    if (lunes) {
        window.location.href = `${evento.target.dataset.url}?semana=${lunes}`;
    }
});
