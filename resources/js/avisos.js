/*
 * Avisos flotantes de toda la app (abajo, al centro): errores de red o del servidor y confirmaciones,
 * opcionalmente con una acción ("Deshacer"). Se retiran solos; pasar el mouse o el foco los detiene.
 * También da botón de cerrar y retiro automático al aviso flash del servidor ([data-aviso-flash]).
 */

const DURACION = 6000;

function zona() {
    let contenedor = document.getElementById('avisos-flotantes');

    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'avisos-flotantes';
        contenedor.className = 'avisos-flotantes';
        document.body.append(contenedor);
    }

    return contenedor;
}

function botonCerrar(alCerrar) {
    const boton = document.createElement('button');

    boton.type = 'button';
    boton.className = 'aviso-cerrar';
    boton.setAttribute('aria-label', 'Cerrar aviso');
    boton.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
    boton.addEventListener('click', alCerrar);

    return boton;
}

/** Retira el aviso a los `ms` milisegundos, salvo mientras tenga el mouse o el foco encima. */
function retirarSolo(aviso, cerrar, ms) {
    let temporizador = setTimeout(cerrar, ms);
    const detener = () => clearTimeout(temporizador);
    const reanudar = () => {
        clearTimeout(temporizador);
        temporizador = setTimeout(cerrar, 2500);
    };

    aviso.addEventListener('mouseenter', detener);
    aviso.addEventListener('focusin', detener);
    aviso.addEventListener('mouseleave', reanudar);
    aviso.addEventListener('focusout', (evento) => {
        if (!aviso.contains(evento.relatedTarget)) reanudar();
    });

    return detener;
}

/**
 * Muestra un aviso flotante.
 * `alRetirar` se llama cuando el aviso se va sin usar ninguna acción (solo, o con el botón de cerrar).
 * Con `duracion: null` el aviso queda hasta que se use una acción o se cierre (p. ej. un recordatorio).
 * @param {string} texto
 * @param {{
 *   tipo?: 'info'|'error', detalle?: string, icono?: string,
 *   accion?: { texto: string, alHacer: () => void }, acciones?: Array<{ texto: string, alHacer: () => void }>,
 *   alRetirar?: () => void, duracion?: number|null,
 * }} [opciones]
 * @returns {{ cerrar: () => void, quitar: () => boolean, botonAccion: HTMLButtonElement|null }}
 */
export function mostrarAviso(texto, {
    tipo = 'info', detalle = null, icono = null, accion = null, acciones = null, alRetirar = null, duracion = DURACION,
} = {}) {
    const aviso = document.createElement('div');
    const cuerpo = document.createElement('div');
    const mensaje = document.createElement('span');
    let detener = () => {};
    let cerrado = false;

    const quitar = () => {
        if (cerrado) return false;

        cerrado = true;
        detener();
        aviso.remove();

        return true;
    };

    const cerrar = () => {
        if (quitar()) alRetirar?.();
    };

    aviso.className = `aviso-flotante${tipo === 'error' ? ' aviso-error' : ''}`;
    aviso.setAttribute('role', tipo === 'error' ? 'alert' : 'status');

    if (icono) {
        const simbolo = document.createElement('i');

        simbolo.className = `bi bi-${icono} aviso-icono`;
        simbolo.setAttribute('aria-hidden', 'true');
        aviso.append(simbolo);
    }

    cuerpo.className = 'aviso-cuerpo';
    mensaje.className = 'aviso-texto';
    mensaje.textContent = texto;
    cuerpo.append(mensaje);

    if (detalle) {
        const linea = document.createElement('span');

        linea.className = 'aviso-detalle';
        linea.textContent = detalle;
        cuerpo.append(linea);
    }

    aviso.append(cuerpo);

    const botones = (acciones ?? (accion ? [accion] : [])).map((una) => {
        const boton = document.createElement('button');

        boton.type = 'button';
        boton.className = 'aviso-accion';
        boton.textContent = una.texto;
        boton.addEventListener('click', () => {
            if (quitar()) una.alHacer();
        });
        aviso.append(boton);

        return boton;
    });

    aviso.append(botonCerrar(cerrar));
    zona().append(aviso);

    if (duracion !== null) detener = retirarSolo(aviso, cerrar, duracion);

    return { cerrar, quitar, botonAccion: botones[0] ?? null };
}

/** El aviso flash del servidor: botón de cerrar y, si no es un error, se retira solo. */
export function prepararAvisosFlash() {
    document.querySelectorAll('[data-aviso-flash]').forEach((aviso) => {
        const cerrar = () => aviso.remove();

        aviso.append(botonCerrar(cerrar));

        if (!aviso.classList.contains('aviso-error')) retirarSolo(aviso, cerrar, DURACION);
    });
}
