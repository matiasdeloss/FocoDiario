/**
 * Qué tipos muestra el calendario al entrar. Funciones puras (se prueban en tests/js).
 * Un tipo que el usuario nunca vio (se agregó después de guardar su filtro) arranca activo;
 * uno que vio y desmarcó sigue desmarcado.
 */

/** Tipos que existían antes de que se guardara la lista de "vistos". */
export const TIPOS_ANTIGUOS = ['tarea', 'recordatorio', 'nota', 'sesion'];

/**
 * @param {unknown} guardado  lista de tipos activos guardada (o cualquier otra cosa si no hay)
 * @param {unknown} vistos    lista de tipos que el usuario ya conocía (o cualquier otra cosa si no hay)
 * @param {string[]} tipos    todos los tipos actuales
 */
export function tiposIniciales(guardado, vistos, tipos) {
    if (!Array.isArray(guardado)) {
        return [...tipos];
    }

    const conocidos = Array.isArray(vistos) ? vistos : TIPOS_ANTIGUOS;
    const activos = guardado.filter((tipo) => tipos.includes(tipo));
    const nuevos = tipos.filter((tipo) => !conocidos.includes(tipo) && !activos.includes(tipo));

    return [...activos, ...nuevos];
}
