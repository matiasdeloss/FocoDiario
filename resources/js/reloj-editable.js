/*
 * Reloj que se puede tocar para escribir el tiempo (tarjeta Pomodoro de Hoy y tarjeta principal de Estudio).
 * El reloj es un botón; al activarlo pasa a un campo de texto con el tiempo seleccionado. Enter o salir del
 * campo confirma, Escape cancela. Si lo escrito no es válido se muestra el error y la edición sigue abierta.
 * Solo se edita sin una fase corriendo. Cómo se interpreta el texto está en pomodoro-logica.js.
 */
import { formatearDuracion, formatearTiempo, interpretarTiempo } from './pomodoro-logica.js';

/**
 * @param {HTMLButtonElement} boton El reloj (el texto lo dibuja quien lo usa; aquí solo se maneja la edición).
 * @param {object} opciones
 * @param {() => string} opciones.nombre Nombre de lo que se edita ("Enfoque", "Descanso"...), para las etiquetas.
 * @param {() => number} opciones.segundos Duración actual, en segundos.
 * @param {() => boolean} opciones.puedeEditar false mientras hay una fase en marcha.
 * @param {(seg: number) => void} opciones.confirmar Recibe la duración nueva ya validada.
 * @param {(texto: string) => void} opciones.error Muestra (o borra, con '') el error donde corresponda.
 * @param {(texto: string) => void} opciones.anunciar Avisa el nuevo valor a lectores de pantalla.
 * @returns {{ actualizar: () => void }}
 */
export function hacerEditable(boton, { nombre, segundos, puedeEditar, confirmar, error, anunciar }) {
    const input = document.createElement('input');
    let editando = false;
    let cerrando = false;

    input.type = 'text';
    input.inputMode = 'numeric';
    input.enterKeyHint = 'done';
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.maxLength = 9;
    input.className = `${boton.className} reloj-input`;
    input.hidden = true;
    input.dataset.tamano = 'medio';
    boton.after(input);

    function cerrar() {
        cerrando = true;
        editando = false;
        input.hidden = true;
        input.removeAttribute('aria-invalid');
        boton.hidden = false;
        error('');
        cerrando = false;
    }

    function abrir() {
        if (editando || !puedeEditar()) return;

        editando = true;
        input.value = formatearTiempo(segundos() * 1000);
        input.dataset.tamano = boton.dataset.tamano ?? 'normal';
        input.setAttribute('aria-label', `Tiempo de ${nombre().toLowerCase()}, en minutos y segundos. Enter para guardar, Escape para cancelar`);
        boton.hidden = true;
        input.hidden = false;
        input.focus();
        input.select();
    }

    function guardar() {
        const r = interpretarTiempo(input.value);

        if (!r.ok) {
            input.setAttribute('aria-invalid', 'true');
            error(r.error);

            return false;
        }

        cerrar();

        if (r.seg !== segundos()) confirmar(r.seg);

        anunciar(`Tiempo de ${nombre().toLowerCase()}: ${formatearDuracion(r.seg)}`);
        boton.focus();

        return true;
    }

    boton.addEventListener('click', abrir);

    input.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            guardar();
        } else if (evento.key === 'Escape') {
            evento.preventDefault();
            cerrar();
            boton.focus();
        }
    });
    input.addEventListener('input', () => {
        input.removeAttribute('aria-invalid');
        error('');
    });
    // Salir del campo confirma; si lo escrito no es válido, queda abierto con el error a la vista.
    input.addEventListener('blur', () => {
        if (editando && !cerrando) guardar();
    });

    return {
        /** Se llama en cada dibujo: actualiza etiqueta y ayuda del botón, y cierra la edición si ya no se puede. */
        actualizar() {
            const editable = puedeEditar();
            const texto = boton.textContent.trim();

            boton.setAttribute('aria-disabled', editable ? 'false' : 'true');
            boton.classList.toggle('es-bloqueado', !editable);
            boton.title = editable ? 'Tocá para escribir el tiempo' : 'Terminá la sesión para cambiar el tiempo';
            boton.setAttribute('aria-label', editable ? `Editar tiempo, ${texto}` : `Tiempo, ${texto}. Terminá la sesión para cambiar el tiempo`);

            if (editando && !editable) cerrar();
        },
    };
}
