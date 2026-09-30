/*
 * Confirmación única de la app: un <dialog> con los estilos de .dialogo en lugar de window.confirm.
 * Se usa desde JS (await confirmar(...)), desde formularios con data-confirmar="pregunta"
 * y desde los hx-confirm de HTMX (ver app.js).
 * El foco arranca en "Cancelar" (lo seguro en una acción destructiva) y vuelve al elemento de origen al cerrar.
 */

let dialogo = null;

function crearDialogo() {
    const nuevo = document.createElement('dialog');

    nuevo.className = 'dialogo dialogo-chico confirmacion';
    nuevo.setAttribute('aria-labelledby', 'confirmacion-texto');
    nuevo.innerHTML = `
        <form method="dialog" class="dialogo-cuerpo">
            <p id="confirmacion-texto" class="dialogo-texto"></p>
            <div class="dialogo-pie-acciones">
                <button type="submit" value="no" class="btn btn-foco-suave" data-cancelar>Cancelar</button>
                <button type="submit" value="si" class="btn" data-aceptar></button>
            </div>
        </form>`;
    document.body.append(nuevo);

    // Clic en el fondo = cancelar.
    nuevo.addEventListener('click', (evento) => {
        if (evento.target === nuevo) nuevo.close('no');
    });

    return nuevo;
}

/**
 * @param {string} pregunta
 * @param {{ aceptar?: string, peligro?: boolean }} [opciones]
 * @returns {Promise<boolean>}
 */
export function confirmar(pregunta, { aceptar = 'Eliminar', peligro = true } = {}) {
    dialogo ??= crearDialogo();

    // Si ya hay una pregunta abierta, la nueva la reemplaza (la anterior se da por cancelada).
    if (dialogo.open) dialogo.close('no');

    const origen = document.activeElement;
    const botonAceptar = dialogo.querySelector('[data-aceptar]');

    dialogo.querySelector('#confirmacion-texto').textContent = pregunta;
    botonAceptar.textContent = aceptar;
    botonAceptar.className = `btn ${peligro ? 'btn-foco-peligro' : 'btn-foco'}`;
    dialogo.returnValue = '';

    return new Promise((resolver) => {
        dialogo.addEventListener('close', () => {
            resolver(dialogo.returnValue === 'si');

            if (origen instanceof HTMLElement && origen.isConnected) origen.focus({ preventScroll: true });
        }, { once: true });

        dialogo.showModal();
        dialogo.querySelector('[data-cancelar]').focus();
    });
}

/** Formularios con data-confirmar: preguntan antes de enviarse (reemplaza a los onsubmit="return confirm(...)"). */
export function activarFormulariosConConfirmacion() {
    // En captura, para decidir antes que los demás listeners de submit (p. ej. los de la Agenda).
    document.addEventListener('submit', async (evento) => {
        const formulario = evento.target;

        if (!(formulario instanceof HTMLFormElement) || !formulario.dataset.confirmar) return;

        if (formulario.dataset.confirmado) {
            delete formulario.dataset.confirmado;

            return;
        }

        evento.preventDefault();
        evento.stopImmediatePropagation();

        const { confirmar: pregunta, confirmarAceptar } = formulario.dataset;

        if (await confirmar(pregunta, { aceptar: confirmarAceptar ?? 'Eliminar' })) {
            formulario.dataset.confirmado = '1';
            formulario.requestSubmit(evento.submitter ?? undefined);
        }
    }, true);
}
