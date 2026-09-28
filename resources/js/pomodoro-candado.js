/*
 * Candado simple entre pestañas, sobre localStorage, sin DOM ni red (se prueba con Node).
 *
 * Solo la pestaña que tiene el candado cierra fases y vacía la cola de intervalos hacia el servidor.
 * Las demás se limitan a mostrar el estado compartido. El candado caduca solo (ttl) para que, si la
 * pestaña líder se cierra o el navegador la duerme, otra tome el relevo.
 *
 * No es atómico (localStorage no lo permite): si dos pestañas lo pelean en el mismo instante pueden
 * llegar a registrar el mismo intervalo, y ahí decide la clave idempotente del servidor.
 */
export const TTL_CANDADO_MS = 3000;

function leerCandado(almacen, clave) {
    try {
        return JSON.parse(almacen.getItem(clave));
    } catch {
        return null;
    }
}

/** Devuelve true si esta pestaña (id) tiene el candado, tomándolo o renovándolo si hace falta. */
export function adquirirCandado(almacen, clave, id, ahora, ttl = TTL_CANDADO_MS) {
    const actual = leerCandado(almacen, clave);

    if (actual && actual.id !== id && actual.hasta > ahora) return false;

    // Ya es mío y falta bastante para que caduque: no se reescribe (evita ruido en las otras pestañas).
    if (actual && actual.id === id && actual.hasta - ahora > ttl / 2) return true;

    try {
        almacen.setItem(clave, JSON.stringify({ id, hasta: ahora + ttl }));
    } catch {
        return true; // Sin almacenamiento no hay otras pestañas con las que coordinarse.
    }

    return leerCandado(almacen, clave)?.id === id;
}

/** Suelta el candado solo si es de esta pestaña. */
export function liberarCandado(almacen, clave, id) {
    try {
        if (leerCandado(almacen, clave)?.id === id) almacen.removeItem(clave);
    } catch {
        // Nada que liberar.
    }
}
