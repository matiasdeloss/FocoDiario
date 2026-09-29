/*
 * Nota rápida de Hoy (tarjeta con aspecto de cuaderno): tipo, chips desplegables (fecha y destino), colores siempre visibles en la cabecera,
 * atajos de teclado y autoajuste de la hoja. Sin JavaScript el formulario sigue enviándose con los campos a la vista.
 * El formulario se reemplaza entero con HTMX al guardar: los eventos se delegan en el documento y se
 * vuelve a preparar cada formulario nuevo.
 */
import { chipVisible, etiquetaFecha, fechaEnDias, tipoValido } from './captura-rapida-logica.js';

const SELECTOR_CAPTURA = '[data-nota-rapida] [data-captura]';
const CLAVE_TIPO = 'focodiario.captura.tipo';
const SOPORTA_AJUSTE = typeof CSS !== 'undefined' && CSS.supports?.('field-sizing', 'content');

function leerTipoGuardado() {
    try {
        return tipoValido(window.localStorage.getItem(CLAVE_TIPO));
    } catch {
        return 'nota';
    }
}

function guardarTipo(tipo) {
    try {
        window.localStorage.setItem(CLAVE_TIPO, tipo);
    } catch {
        // Sin almacenamiento (ventana privada, datos bloqueados): se sigue sin recordar el tipo.
    }
}

const tipoElegido = (formulario) => formulario.querySelector('[data-tipo]:checked')?.value ?? 'nota';
const campo = (formulario, nombre) => formulario.querySelector(`[name="${nombre}"]`);

/* ---------- Hoja: crece con el contenido cuando el navegador no lo hace solo ---------- */
function ajustarHoja(formulario) {
    if (SOPORTA_AJUSTE) return;

    const hoja = formulario.querySelector('.hoy-cuaderno-hoja');

    hoja.style.height = 'auto';
    hoja.style.height = `${hoja.scrollHeight}px`;
}

/* ---------- Chips y paneles ---------- */
function panelDe(formulario, nombre) {
    return formulario.querySelector(`[data-panel="${nombre}"]`);
}

function chipDe(formulario, nombre) {
    return formulario.querySelector(`[data-chip="${nombre}"]`);
}

function cerrarPanel(formulario, nombre, devolverFoco = false) {
    const panel = panelDe(formulario, nombre);
    const chip = chipDe(formulario, nombre);

    panel?.classList.remove('es-abierto');
    chip?.setAttribute('aria-expanded', 'false');

    if (devolverFoco) chip?.focus();
}

function cerrarPaneles(formulario, salvo = null) {
    formulario.querySelectorAll('[data-panel]').forEach((panel) => {
        if (panel.dataset.panel !== salvo) cerrarPanel(formulario, panel.dataset.panel);
    });
}

function abrirPanel(formulario, nombre) {
    cerrarPaneles(formulario, nombre);
    panelDe(formulario, nombre).classList.add('es-abierto');
    chipDe(formulario, nombre).setAttribute('aria-expanded', 'true');

    // Lleva el foco al primer campo visible del panel.
    [...panelDe(formulario, nombre).querySelectorAll('input, select')].find((control) => control.offsetParent !== null)?.focus();
}

function alternarPanel(formulario, nombre) {
    if (panelDe(formulario, nombre).classList.contains('es-abierto')) cerrarPanel(formulario, nombre, true);
    else abrirPanel(formulario, nombre);
}

/** Pone al día lo que cada chip muestra (valor, etiqueta accesible, botón para quitar). */
function actualizarChips(formulario) {
    const tipo = tipoElegido(formulario);
    const fecha = campo(formulario, 'fecha').value;
    const hora = tipo === 'recordatorio' ? campo(formulario, 'hora').value : '';
    const textoFecha = etiquetaFecha(fecha, hora);
    const select = campo(formulario, 'contexto_id');
    const hayDestino = select.value !== '';
    const textoDestino = select.selectedOptions[0]?.textContent.trim() || 'Bandeja de entrada';

    chipDe(formulario, 'fecha').querySelector('[data-chip-texto]').textContent = textoFecha;
    chipDe(formulario, 'fecha').setAttribute('aria-label', textoFecha ? `Fecha: ${textoFecha}` : 'Fecha');
    formulario.querySelector('[data-chip-quitar="fecha"]').hidden = textoFecha === '';

    chipDe(formulario, 'destino').querySelector('[data-chip-texto]').textContent = textoDestino;
    chipDe(formulario, 'destino').setAttribute('aria-label', `Destino: ${textoDestino}`);
    formulario.querySelector('[data-chip-quitar="destino"]').hidden = !hayDestino;
}

/** Muestra solo lo que corresponde al tipo elegido; lo escrito en lo oculto se conserva. */
function aplicarTipo(formulario) {
    const tipo = tipoElegido(formulario);

    formulario.querySelectorAll('[data-para]').forEach((nodo) => {
        nodo.toggleAttribute('data-oculto', !chipVisible(nodo.dataset.para, tipo));
    });
    formulario.querySelectorAll('[data-panel][data-oculto]').forEach((panel) => cerrarPanel(formulario, panel.dataset.panel));
    teñirGuardar(formulario);
    actualizarChips(formulario);
}

/** El color elegido (solo en notas) tiñe el botón Guardar; los campos no cambian de color. */
function teñirGuardar(formulario) {
    const color = campo(formulario, 'color').value;

    if (color && tipoElegido(formulario) === 'nota') formulario.dataset.color = color;
    else delete formulario.dataset.color;
}

function fijarColor(formulario, color) {
    campo(formulario, 'color').value = color;
    formulario.querySelectorAll('[data-color-nota]').forEach((boton) => {
        boton.setAttribute('aria-pressed', boton.dataset.colorNota === color ? 'true' : 'false');
    });
    teñirGuardar(formulario);
}

/** Vacía todo menos el tipo elegido. */
function descartar(formulario) {
    formulario.querySelectorAll('input[type="text"], input[type="date"], input[type="time"], textarea').forEach((entrada) => { entrada.value = ''; });
    campo(formulario, 'contexto_id').value = '';
    cerrarPaneles(formulario);
    fijarColor(formulario, '');
    ajustarHoja(formulario);
    campo(formulario, 'titulo').focus();
}

function hayTexto(formulario) {
    return campo(formulario, 'titulo').value !== '' || campo(formulario, 'descripcion').value !== '';
}

/* ---------- Eventos ---------- */
document.addEventListener('input', (evento) => {
    const formulario = evento.target.closest?.(SELECTOR_CAPTURA);

    if (!formulario) return;

    if (evento.target.matches('.hoy-cuaderno-hoja')) ajustarHoja(formulario);
    if (evento.target.matches('[name="fecha"], [name="hora"]')) actualizarChips(formulario);
});

document.addEventListener('change', (evento) => {
    const formulario = evento.target.closest?.(SELECTOR_CAPTURA);

    if (!formulario) return;

    if (evento.target.matches('[data-tipo]')) {
        guardarTipo(tipoElegido(formulario));
        aplicarTipo(formulario);
    } else if (evento.target.matches('[name="fecha"], [name="hora"], [name="contexto_id"]')) {
        actualizarChips(formulario);
    }
});

document.addEventListener('focusin', (evento) => {
    const formulario = evento.target.closest?.(SELECTOR_CAPTURA);

    if (formulario && evento.target.matches('.hoy-cuaderno-titulo, .hoy-cuaderno-hoja')) ajustarHoja(formulario);
});

document.addEventListener('click', (evento) => {
    const formulario = evento.target.closest?.(SELECTOR_CAPTURA);

    if (!formulario) return;

    const chip = evento.target.closest('[data-chip]');

    if (chip) {
        alternarPanel(formulario, chip.dataset.chip);

        return;
    }

    const quitar = evento.target.closest('[data-chip-quitar]');

    if (quitar) {
        const que = quitar.dataset.chipQuitar;

        if (que === 'fecha') {
            campo(formulario, 'fecha').value = '';
            campo(formulario, 'hora').value = '';
            actualizarChips(formulario);
        } else if (que === 'destino') {
            campo(formulario, 'contexto_id').value = '';
            actualizarChips(formulario);
        }

        chipDe(formulario, que).focus();

        return;
    }

    const atajo = evento.target.closest('[data-fecha-atajo]');

    if (atajo) {
        campo(formulario, 'fecha').value = fechaEnDias(Number(atajo.dataset.fechaAtajo));
        actualizarChips(formulario);

        return;
    }

    const color = evento.target.closest('[data-color-nota]');

    if (color) {
        // Volver a tocar el color elegido lo quita.
        fijarColor(formulario, campo(formulario, 'color').value === color.dataset.colorNota ? '' : color.dataset.colorNota);

        return;
    }

    if (evento.target.closest('[data-nota-descartar]')) descartar(formulario);
});

document.addEventListener('keydown', (evento) => {
    const formulario = evento.target.closest?.(SELECTOR_CAPTURA);

    if (!formulario || evento.isComposing) return;

    // Ctrl+Enter (o Cmd+Enter) guarda desde cualquier campo.
    if (evento.key === 'Enter' && (evento.ctrlKey || evento.metaKey)) {
        evento.preventDefault();
        formulario.requestSubmit();

        return;
    }

    // Enter en el título guarda si hay título; si está vacío, pasa a la descripción.
    if (evento.key === 'Enter' && !evento.shiftKey && evento.target.matches('.hoy-cuaderno-titulo')) {
        evento.preventDefault();

        if (evento.target.value.trim() !== '') formulario.requestSubmit();
        else campo(formulario, 'descripcion').focus();

        return;
    }

    if (evento.key !== 'Escape') return;

    // Dentro de un chip abierto (o sobre su botón): se cierra y el foco vuelve al chip.
    const panel = evento.target.closest('[data-panel]');
    const chip = evento.target.closest('[data-chip]');
    const nombre = panel?.dataset.panel ?? (chip?.getAttribute('aria-expanded') === 'true' ? chip.dataset.chip : null);

    if (nombre) {
        evento.preventDefault();
        cerrarPanel(formulario, nombre, true);

        return;
    }

    // En el título o la descripción, descarta lo escrito.
    if (evento.target.matches('.hoy-cuaderno-titulo, .hoy-cuaderno-hoja') && hayTexto(formulario)) {
        evento.preventDefault();
        descartar(formulario);
    }
});

/* ---------- Preparar cada formulario (al cargar y tras cada reemplazo de HTMX) ---------- */
function prepararFormularios() {
    document.querySelectorAll(SELECTOR_CAPTURA).forEach((formulario) => {
        if (formulario.dataset.preparado === '1') return;

        formulario.dataset.preparado = '1';

        // El tipo recordado solo se aplica al abrir Hoy: tras guardar o con errores manda el que envió el servidor.
        if (!('tipoServidor' in formulario.dataset)) {
            const radio = formulario.querySelector(`[data-tipo][value="${leerTipoGuardado()}"]`);

            if (radio) radio.checked = true;
        }

        aplicarTipo(formulario);
        ajustarHoja(formulario);

        if ('recienGuardado' in formulario.dataset || formulario.querySelector('[aria-invalid="true"]')) {
            (formulario.querySelector('[data-panel].es-abierto [aria-invalid="true"]') ?? campo(formulario, 'titulo')).focus();
        }
    });
}

document.addEventListener('DOMContentLoaded', prepararFormularios);
document.addEventListener('htmx:afterSettle', prepararFormularios);
