/*
 * Lógica pura de la pista "Usa el color de <contexto>" de los diálogos de nota y tarea.
 * Una nota o tarea sin color propio ("Sin color") se ve con el del contexto (o el de su ancestro más cercano con color).
 */

/**
 * @param {{ colorPropio?: string, clave?: string, origen?: string }} entrada
 *   colorPropio: valor del color elegido ('' = "Sin color"); clave y origen vienen de la opción elegida del selector de contexto.
 * @returns {{ texto: string, fondo: string, marca: string } | null} null si no hay nada que mostrar.
 */
export function pistaDeColor({ colorPropio = '', clave = '', origen = '' } = {}) {
    if (colorPropio !== '' || clave === '' || origen === '') return null;

    return {
        texto: `Usa el color de ${origen}`,
        fondo: `var(--actividad-${clave}-fondo)`,
        marca: `var(--actividad-${clave}-acento)`,
    };
}
