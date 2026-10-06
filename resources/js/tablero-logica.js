/*
 * Lógica pura del tablero Kanban: las tarjetas son tareas y notas (data-tipo) y cada tipo se mueve con su ruta.
 */

/** Identificador de una tarjeta dentro del orden de una columna: "tarea:12" o "nota:5". */
export function fichaDeTarjeta(tipo, id) {
    return `${tipo === 'nota' ? 'nota' : 'tarea'}:${Number(id)}`;
}

/**
 * Ruta para mover una tarjeta de columna. `rutas` son los data-* del tablero:
 * urlColumna (tareas) y urlColumnaNota (notas), con "__ID__" donde va el id.
 */
export function urlDeMovimiento(tipo, id, rutas) {
    const molde = tipo === 'nota' ? rutas.urlColumnaNota : rutas.urlColumna;

    return molde.replace('__ID__', String(id));
}

/**
 * Datos de edición de una nota con la columna que muestra el tablero. El `data-nota` se renderiza al cargar la página y
 * queda viejo si la tarjeta se movió después (arrastre o flechas): si la tarjeta está en una columna, esa es la vigente.
 */
export function datosConColumnaVigente(datos, columnaEnTablero) {
    const columna = Number(columnaEnTablero);

    return columnaEnTablero && Number.isFinite(columna) ? { ...datos, columna_id: columna } : datos;
}
