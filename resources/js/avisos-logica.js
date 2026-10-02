/*
 * Lógica de los avisos flotantes, sin DOM (se prueba con Node): variantes, duración, rol accesible,
 * límite de avisos visibles y lectura de lo que llega por el evento `mostrar-aviso` (HX-Trigger).
 */

/** Cuántos avisos se ven a la vez; al pasarse se retira el más viejo que no sea fijo. */
export const MAX_VISIBLES = 4;

/** Retiro tras el último hover/foco, en milisegundos. */
export const REANUDAR_TRAS = 2500;

/**
 * Una variante por tipo de mensaje. `duracion: null` = fijo hasta que se use una acción o se cierre.
 * El rol es `alert` solo para errores (se anuncian al instante); el resto es `status`.
 */
export const VARIANTES = {
    exito: { icono: 'check-circle-fill', duracion: 5000, rol: 'status' },
    info: { icono: 'info-circle-fill', duracion: 5000, rol: 'status' },
    aviso: { icono: 'exclamation-triangle-fill', duracion: 7000, rol: 'status' },
    error: { icono: 'exclamation-octagon-fill', duracion: 8000, rol: 'alert' },
    recordatorio: { icono: 'bell-fill', duracion: null, rol: 'status' },
};

/** El tipo si es conocido; si no, `info`. */
export function normalizarTipo(tipo) {
    return Object.hasOwn(VARIANTES, tipo) ? tipo : 'info';
}

/**
 * Configuración final de un aviso: la de su variante, con `duracion` e `icono` propios si se pasan.
 * `duracion: undefined` usa la de la variante; `null` lo deja fijo.
 */
export function configuracion(tipo, { duracion, icono } = {}) {
    const clave = normalizarTipo(tipo);
    const variante = VARIANTES[clave];

    return {
        tipo: clave,
        rol: variante.rol,
        icono: icono || variante.icono,
        duracion: duracion === undefined ? variante.duracion : duracion,
    };
}

/**
 * Índice (en orden de llegada) del aviso a retirar para no pasar de `max`, o -1 si no hace falta.
 * Los fijos (recordatorios, o con duración nula) y el más nuevo no se retiran por límite.
 * @param {Array<{ fijo: boolean }>} lista
 */
export function indiceARetirar(lista, max = MAX_VISIBLES) {
    if (lista.length <= max) return -1;

    // El último es el recién llegado: nunca es el que se retira.
    return lista.slice(0, -1).findIndex((aviso) => !aviso.fijo);
}

/**
 * Convierte el `detail` del evento `mostrar-aviso` en una lista de avisos válidos.
 * HTMX entrega el objeto del encabezado tal cual; un arreglo llega como `{ value: [...] }`.
 * @returns {Array<{ tipo: string, texto: string, detalle: string|null }>}
 */
export function avisosDeEvento(detalle) {
    if (!detalle || typeof detalle !== 'object') return [];

    const crudos = Array.isArray(detalle) ? detalle : Array.isArray(detalle.value) ? detalle.value : [detalle];

    return crudos
        .filter((uno) => uno && typeof uno === 'object' && typeof uno.texto === 'string' && uno.texto.trim() !== '')
        .map((uno) => ({
            tipo: normalizarTipo(uno.tipo),
            texto: uno.texto,
            detalle: typeof uno.detalle === 'string' && uno.detalle !== '' ? uno.detalle : null,
        }));
}

/** Clave para no repetir el mismo aviso (mismo tipo y texto) mientras ya se ve. */
export function claveDeAviso(tipo, texto) {
    return `${normalizarTipo(tipo)}|${texto}`;
}
