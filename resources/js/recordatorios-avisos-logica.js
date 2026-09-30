/*
 * Lógica pura de los toasts de recordatorios (sin DOM ni red), para poder probarla con Node.
 * Un aviso se identifica por id + hora programada ("12@2026-09-30T14:55"): si se pospone, es un aviso nuevo.
 */

/** Cantidad de avisos cerrados que se recuerdan (los más viejos se olvidan). */
export const MAXIMO_CERRADOS = 100;

const LARGO_DESCRIPCION = 80;

export const claveDe = (recordatorio) => `${recordatorio.id}@${recordatorio.recordar_en}`;

/**
 * Qué toasts abrir y cuáles quitar tras una consulta al servidor (o un cambio en otra pestaña).
 * @param {Array<{ id: number, recordar_en: string }>} vencidos  lo último que devolvió /recordatorios/vencidos
 * @param {string[]} cerrados  claves de los avisos ya cerrados o resueltos (en cualquier pestaña)
 * @param {Set<string>} abiertas  claves de los toasts que esta pestaña tiene a la vista
 */
export function planificar(vencidos, cerrados, abiertas) {
    const vigentes = new Set(vencidos.map(claveDe));
    const yaCerrados = new Set(cerrados);

    return {
        mostrar: vencidos.filter((r) => !yaCerrados.has(claveDe(r)) && !abiertas.has(claveDe(r))),
        // Ya no vence (se marcó en otra pantalla, se pospuso o pasó el día) o se cerró en otra pestaña.
        quitar: [...abiertas].filter((clave) => !vigentes.has(clave) || yaCerrados.has(clave)),
    };
}

/** Línea secundaria del toast: la hora y, si hay, el comienzo de la descripción. */
export function detalleDe(recordatorio) {
    const descripcion = (recordatorio.descripcion ?? '').replace(/\s+/g, ' ').trim();
    const corta = descripcion.length > LARGO_DESCRIPCION ? `${descripcion.slice(0, LARGO_DESCRIPCION - 1).trimEnd()}…` : descripcion;

    return [recordatorio.hora, corta].filter(Boolean).join(' · ');
}

/** Agrega una clave a los cerrados (sin repetir) y conserva solo las `maximo` más recientes. */
export function agregarCerrado(cerrados, clave, maximo = MAXIMO_CERRADOS) {
    return [...cerrados.filter((otra) => otra !== clave), clave].slice(-maximo);
}
