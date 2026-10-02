/*
 * Lógica del tema claro/oscuro, sin DOM ni red (se prueba con Node).
 *
 * El tema elegido a mano se guarda en localStorage con la clave CLAVE_TEMA. Sin elección guardada se sigue
 * la preferencia del sistema.
 */
export const CLAVE_TEMA = 'foco-tema';

/** Devuelve 'light' o 'dark' si el valor guardado es válido; si no, null (sin elección manual). */
export function temaGuardado(valor) {
    return valor === 'light' || valor === 'dark' ? valor : null;
}

/** Tema que se ve en pantalla: la elección guardada o, sin ella, la del sistema. */
export function temaEfectivo(guardado, sistemaOscuro) {
    return temaGuardado(guardado) ?? (sistemaOscuro ? 'dark' : 'light');
}
