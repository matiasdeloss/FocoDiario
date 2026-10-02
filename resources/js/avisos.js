/*
 * Avisos flotantes: el único sistema de confirmaciones y mensajes de la app.
 *
 * Una variante por tipo de mensaje (ver avisos-logica.js): exito, info, aviso, error y recordatorio.
 * Se apilan arriba al centro (a todo el ancho en celular), el más nuevo debajo, hasta
 * MAX_VISIBLES. Se retiran solos según su variante; el mouse o el foco encima los detienen y todos
 * llevan botón de cerrar. Opcionalmente traen acciones ("Deshacer", "Listo").
 *
 * Desde el servidor llegan de dos formas:
 *  - redirección: el layout deja un nodo oculto [data-aviso-flash] que prepararAvisosFlash() convierte en aviso;
 *  - HTMX: el encabezado HX-Trigger {"mostrar-aviso": {...}} dispara el evento `mostrar-aviso` (ver App\Support\Aviso).
 */
import {
    MAX_VISIBLES, REANUDAR_TRAS, avisosDeEvento, claveDeAviso, configuracion, indiceARetirar,
} from './avisos-logica.js';

/** Avisos visibles, del más viejo al más nuevo. */
const activos = [];

function zona() {
    let contenedor = document.getElementById('avisos-flotantes');

    // El layout trae el contenedor; esto es solo un respaldo para páginas sin él.
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'avisos-flotantes';
        contenedor.className = 'avisos-flotantes';
        contenedor.setAttribute('popover', 'manual');
        document.body.append(contenedor);
    }

    return contenedor;
}

/**
 * El contenedor es un popover manual: vive en la capa superior del navegador. Un modal (<dialog>) abierto
 * después queda por encima y, además, deja inerte todo lo que no está dentro de él. Por eso, con un modal
 * abierto el contenedor se muda dentro del modal (sigue en la capa superior, pero por encima y con los
 * botones del aviso activos) y vuelve al <body> cuando el modal se cierra.
 */
function ponerAlFrente(contenedor) {
    const modal = document.querySelector('dialog:modal');
    const destino = modal ?? document.body;

    try {
        // Mudar el nodo lo quita de la capa superior: se vuelve a mostrar después.
        if (contenedor.parentElement !== destino) destino.append(contenedor);
        if (typeof contenedor.showPopover === 'function' && !contenedor.matches(':popover-open')) contenedor.showPopover();
    } catch {
        // Sin soporte de popover: queda con position: fixed y z-index, que alcanza fuera de modales.
    }
}

/** Si hay avisos a la vista cuando un modal se abre o se cierra, el contenedor cambia de lugar. */
function seguirModales() {
    new MutationObserver(() => {
        const contenedor = document.getElementById('avisos-flotantes');

        if (contenedor?.childElementCount) ponerAlFrente(contenedor);
        else if (contenedor && contenedor.parentElement !== document.body && !document.querySelector('dialog:modal')) ponerAlFrente(contenedor);
    }).observe(document.body, { subtree: true, attributes: true, attributeFilter: ['open'] });
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
        temporizador = setTimeout(cerrar, REANUDAR_TRAS);
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
 * `alRetirar` se llama cuando el aviso se va sin usar ninguna acción (solo, por el límite de avisos o con la ×).
 * `duracion` por defecto es la de la variante; con `null` queda hasta que se use una acción o se cierre.
 * Un aviso igual (mismo tipo y texto, sin acciones) que ya se ve no se repite.
 * @param {string} texto
 * @param {{
 *   tipo?: 'exito'|'info'|'aviso'|'error'|'recordatorio', detalle?: string, icono?: string,
 *   accion?: { texto: string, alHacer: () => void }, acciones?: Array<{ texto: string, alHacer: () => void }>,
 *   alRetirar?: () => void, duracion?: number|null,
 * }} [opciones]
 * @returns {{ cerrar: () => void, quitar: () => boolean, botonAccion: HTMLButtonElement|null }}
 */
export function mostrarAviso(texto, {
    tipo = 'info', detalle = null, icono = null, accion = null, acciones = null, alRetirar = null, duracion,
} = {}) {
    const ajustes = configuracion(tipo, { duracion, icono });
    const lista = acciones ?? (accion ? [accion] : []);
    const clave = claveDeAviso(ajustes.tipo, texto);

    if (lista.length === 0) {
        const repetido = activos.find((uno) => uno.clave === clave);

        if (repetido) return repetido.manejo;
    }

    const aviso = document.createElement('div');
    const cuerpo = document.createElement('div');
    const mensaje = document.createElement('span');
    const registro = { clave, fijo: ajustes.duracion === null, manejo: null };
    let detener = () => {};
    let cerrado = false;

    const quitar = () => {
        if (cerrado) return false;

        cerrado = true;
        detener();
        aviso.remove();
        activos.splice(activos.indexOf(registro), 1);

        return true;
    };

    const cerrar = () => {
        if (quitar()) alRetirar?.();
    };

    aviso.className = `aviso-flotante aviso-${ajustes.tipo}`;
    aviso.setAttribute('role', ajustes.rol);

    const simbolo = document.createElement('i');

    simbolo.className = `bi bi-${ajustes.icono} aviso-icono`;
    simbolo.setAttribute('aria-hidden', 'true');
    aviso.append(simbolo);

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

    const botones = lista.map((una) => {
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

    registro.manejo = { cerrar, quitar, botonAccion: botones[0] ?? null };
    activos.push(registro);
    const contenedor = zona();

    contenedor.append(aviso);
    ponerAlFrente(contenedor);

    if (ajustes.duracion !== null) detener = retirarSolo(aviso, cerrar, ajustes.duracion);

    // Al pasarse del límite se retira el más viejo que no sea fijo (como si hubiera vencido).
    const sobra = indiceARetirar(activos, MAX_VISIBLES);

    if (sobra !== -1) activos[sobra].manejo.cerrar();

    return registro.manejo;
}

const con = (tipo) => (texto, opciones = {}) => mostrarAviso(texto, { ...opciones, tipo });

/** Un método por variante: `aviso.exito('Tarea creada.')`, `aviso.error('No se pudo guardar.')`. */
export const aviso = {
    exito: con('exito'),
    info: con('info'),
    aviso: con('aviso'),
    error: con('error'),
    recordatorio: con('recordatorio'),
};

/**
 * Los avisos del servidor: cada nodo oculto [data-aviso-flash] (lo deja el layout con la sesión flash)
 * pasa a ser un aviso flotante. El evento `mostrar-aviso` (HX-Trigger) muestra los de las respuestas HTMX.
 */
export function prepararAvisosFlash() {
    seguirModales();
    document.querySelectorAll('[data-aviso-flash]').forEach((nodo) => {
        const { tipo, texto, detalle } = nodo.dataset;

        nodo.remove();

        if (texto) mostrarAviso(texto, { tipo, detalle });
    });
}

/** Escucha `mostrar-aviso` (HX-Trigger): admite un aviso o un arreglo de avisos. */
export function escucharAvisosDelServidor() {
    document.addEventListener('mostrar-aviso', (evento) => {
        avisosDeEvento(evento.detail).forEach(({ tipo, texto, detalle }) => mostrarAviso(texto, { tipo, detalle }));
    });
}
