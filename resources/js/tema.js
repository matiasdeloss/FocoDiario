import { CLAVE_TEMA, temaEfectivo, temaGuardado } from './tema-logica.js';

/*
 * Interruptor de tema del menú: dos botones (claro / oscuro) con aria-pressed. Elegir uno fija
 * data-theme en <html> y lo guarda en localStorage; mientras no haya elección se sigue al sistema
 * y el botón marcado se actualiza si el sistema cambia. El script del <head> de layouts/app.blade.php
 * aplica el tema guardado antes de pintar para evitar el destello.
 */
const raiz = document.documentElement;
const consulta = window.matchMedia('(prefers-color-scheme: dark)');

function leerGuardado() {
    try {
        return temaGuardado(localStorage.getItem(CLAVE_TEMA));
    } catch {
        return null;
    }
}

function guardar(tema) {
    try {
        localStorage.setItem(CLAVE_TEMA, tema);
    } catch {
        // Sin almacenamiento (modo privado, bloqueado): el tema vale solo hasta recargar.
    }
}

function marcarBotones() {
    const efectivo = temaEfectivo(leerGuardado(), consulta.matches);

    document.querySelectorAll('[data-tema-opcion]').forEach((boton) => {
        boton.setAttribute('aria-pressed', String(boton.dataset.temaOpcion === efectivo));
    });
}

document.addEventListener('click', (evento) => {
    const boton = evento.target.closest?.('[data-tema-opcion]');

    if (!boton) return;

    const tema = temaGuardado(boton.dataset.temaOpcion);

    if (!tema) return;

    raiz.dataset.theme = tema;
    guardar(tema);
    marcarBotones();
});

// Sin elección guardada, el botón marcado sigue al sistema.
consulta.addEventListener('change', () => {
    if (!leerGuardado()) marcarBotones();
});

marcarBotones();
