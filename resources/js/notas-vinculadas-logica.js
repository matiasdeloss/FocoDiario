/*
 * Lógica pura de las notas vinculadas a una tarea (diálogo de la tarea): lista de notas elegidas y resultados de búsqueda.
 */

/** Agrega una nota a las vinculadas sin repetirla (devuelve una lista nueva). */
export function vincular(vinculadas, nota) {
    return vinculadas.some((v) => v.id === nota.id) ? vinculadas : [...vinculadas, { id: nota.id, titulo: nota.titulo }];
}

/** Quita una nota de las vinculadas por id (devuelve una lista nueva). */
export function desvincular(vinculadas, id) {
    return vinculadas.filter((v) => v.id !== id);
}

/** Resultados de la búsqueda sin las notas que ya están vinculadas. */
export function candidatas(resultados, vinculadas) {
    const ya = new Set(vinculadas.map((v) => v.id));

    return resultados.filter((nota) => !ya.has(nota.id));
}

/** Ids a enviar al guardar la tarea. */
export function idsDe(vinculadas) {
    return vinculadas.map((v) => v.id);
}
