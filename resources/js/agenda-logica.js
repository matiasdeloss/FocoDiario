/*
 * Lógica pura de la Agenda (sin DOM ni red), para poder probarla con Node: tests/js/agenda-logica.test.mjs.
 * Grilla de la hoja del día, listas con casillas, horas y semanas.
 */

export const COLUMNAS = 12;
export const ALTO_MAXIMO = 100;
export const FILA_MAXIMA = 2000;
export const ANCHO_MINIMO = 2;
export const ALTO_MINIMO = 4;

const entero = (valor, respaldo) => {
    const numero = Math.trunc(Number(valor));

    return Number.isFinite(numero) ? numero : respaldo;
};

const limitar = (valor, minimo, maximo) => Math.min(Math.max(valor, minimo), maximo);

/* ---------- Grilla ---------- */

/** Deja una posición y un tamaño dentro de la hoja: 12 columnas, sin salirse por la derecha. */
export function ajustarLayout({ x = 0, y = 0, ancho = 6, alto = 8 } = {}) {
    const anchoFinal = limitar(entero(ancho, 6), ANCHO_MINIMO, COLUMNAS);
    const altoFinal = limitar(entero(alto, 8), ALTO_MINIMO, ALTO_MAXIMO);

    return {
        x: limitar(entero(x, 0), 0, COLUMNAS - anchoFinal),
        y: limitar(entero(y, 0), 0, FILA_MAXIMA),
        ancho: anchoFinal,
        alto: altoFinal,
    };
}

export function moverLayout(layout, dx, dy) {
    return ajustarLayout({ ...layout, x: layout.x + dx, y: layout.y + dy });
}

export function redimensionarLayout(layout, dAncho, dAlto) {
    return ajustarLayout({ ...layout, ancho: layout.ancho + dAncho, alto: layout.alto + dAlto });
}

/** Orden de lectura: de arriba abajo y de izquierda a derecha (el orden en que se apilan en el celular). */
export function ordenarPorPosicion(cajas) {
    return [...cajas].sort((a, b) => a.y - b.y || a.x - b.x || a.id - b.id);
}

/**
 * Sube o baja una caja en el orden de lectura. Las cajas intercambian sus lugares (y, x) en la hoja,
 * conservando cada una su propio ancho y alto. Devuelve solo las cajas que cambian: [{id, x, y, ancho, alto}].
 * direccion: -1 sube, 1 baja.
 */
export function reordenar(cajas, id, direccion) {
    const orden = ordenarPorPosicion(cajas);
    const indice = orden.findIndex((caja) => caja.id === id);
    const destino = indice + direccion;

    if (indice === -1 || destino < 0 || destino >= orden.length) {
        return [];
    }

    const lugares = orden.map((caja) => ({ x: caja.x, y: caja.y }));
    const nuevoOrden = [...orden];
    [nuevoOrden[indice], nuevoOrden[destino]] = [nuevoOrden[destino], nuevoOrden[indice]];

    return nuevoOrden
        .map((caja, posicion) => ({ ...ajustarLayout({ ...caja, ...lugares[posicion] }), id: caja.id }))
        .filter((nueva) => {
            const anterior = orden.find((caja) => caja.id === nueva.id);

            return anterior.x !== nueva.x || anterior.y !== nueva.y;
        });
}

/* ---------- Listas con casillas ---------- */

export function normalizarItems(items) {
    if (!Array.isArray(items)) {
        return [];
    }

    return items.map((item) => ({ texto: String(item?.texto ?? ''), hecho: Boolean(item?.hecho) }));
}

export function alternarItem(items, indice) {
    return normalizarItems(items).map((item, i) => (i === indice ? { ...item, hecho: !item.hecho } : item));
}

export function cambiarTextoItem(items, indice, texto) {
    return normalizarItems(items).map((item, i) => (i === indice ? { ...item, texto: String(texto) } : item));
}

/** Agrega un ítem vacío después de la posición dada (o al final si no se indica). */
export function insertarItemDespues(items, indice = items.length - 1) {
    const lista = normalizarItems(items);
    const posicion = limitar(indice + 1, 0, lista.length);

    return [...lista.slice(0, posicion), { texto: '', hecho: false }, ...lista.slice(posicion)];
}

export function quitarItem(items, indice) {
    return normalizarItems(items).filter((_, i) => i !== indice);
}

export function contarItems(items) {
    const lista = normalizarItems(items).filter((item) => item.texto.trim() !== '');

    return { total: lista.length, hechos: lista.filter((item) => item.hecho).length };
}

/** Pasa de texto a lista: un ítem por renglón con contenido. */
export function textoALista(texto) {
    return String(texto ?? '')
        .split(/\r?\n/)
        .map((linea) => linea.replace(/^\s*(?:[-*•]|\[[ xX]?\])\s*/, '').trim())
        .filter((linea) => linea !== '')
        .map((linea) => ({ texto: linea, hecho: false }));
}

/** Pasa de lista a texto: un renglón por ítem. */
export function listaATexto(items) {
    return normalizarItems(items)
        .filter((item) => item.texto.trim() !== '')
        .map((item) => item.texto)
        .join('\n');
}

/* ---------- Horas ---------- */

export function horaValida(hora) {
    return typeof hora === 'string' && /^([01]\d|2[0-3]):[0-5]\d$/.test(hora);
}

/** Mensaje de error si las horas no tienen sentido, o null si están bien (ambas son opcionales). */
export function errorDeHoras(inicio, fin) {
    const hayInicio = inicio !== null && inicio !== undefined && inicio !== '';
    const hayFin = fin !== null && fin !== undefined && fin !== '';

    if (hayInicio && !horaValida(inicio)) return 'La hora de inicio no es válida.';
    if (hayFin && !horaValida(fin)) return 'La hora de fin no es válida.';
    if (hayFin && !hayInicio) return 'Para poner una hora de fin, primero elegí la hora de inicio.';
    if (hayFin && fin <= inicio) return 'La hora de fin tiene que ser posterior a la de inicio.';

    return null;
}

/* ---------- Semanas ---------- */

const MS_DIA = 86_400_000;

function aUtc(iso) {
    const coincidencia = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso));

    if (!coincidencia) {
        return null;
    }

    const [, anio, mes, dia] = coincidencia.map(Number);
    const fecha = new Date(Date.UTC(anio, mes - 1, dia));

    // Rechaza fechas que JavaScript corrige solo (por ejemplo, 2026-02-31).
    return fecha.getUTCFullYear() === anio && fecha.getUTCMonth() === mes - 1 && fecha.getUTCDate() === dia ? fecha : null;
}

const aIso = (fecha) => fecha.toISOString().slice(0, 10);

/** Lunes de la semana de una fecha AAAA-MM-DD, o null si la fecha no es válida. */
export function lunesDe(iso) {
    const fecha = aUtc(iso);

    if (!fecha) {
        return null;
    }

    const desdeLunes = (fecha.getUTCDay() + 6) % 7;

    return aIso(new Date(fecha.getTime() - desdeLunes * MS_DIA));
}

export function sumarDias(iso, dias) {
    const fecha = aUtc(iso);

    return fecha ? aIso(new Date(fecha.getTime() + dias * MS_DIA)) : null;
}

/* ---------- Guardado ---------- */

/** Espera (ms) antes de reintentar un guardado que falló; null cuando ya no se reintenta. */
export function esperaDeReintento(intento, esperas = [1000, 3000, 8000]) {
    return intento >= 0 && intento < esperas.length ? esperas[intento] : null;
}
