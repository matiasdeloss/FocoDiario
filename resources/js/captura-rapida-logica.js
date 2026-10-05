/* Lógica pura de la nota rápida de Hoy (sin DOM): textos cortos de fecha y hora para el chip. */

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/** "2026-10-12" a partir de una Date local. */
export function fechaISO(fecha) {
    const dos = (n) => String(n).padStart(2, '0');

    return `${fecha.getFullYear()}-${dos(fecha.getMonth() + 1)}-${dos(fecha.getDate())}`;
}

/** Fecha ISO de hoy más N días (en hora local). */
export function fechaEnDias(dias, hoy = new Date()) {
    const dia = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() + dias);

    return fechaISO(dia);
}

/** "Hoy", "Mañana", "12 oct" (con año si es de otro año) y la hora si hay: "12 oct 10:00". Vacío si no hay fecha válida. */
export function etiquetaFecha(fecha, hora = '', hoy = new Date()) {
    const partes = /^(\d{4})-(\d{2})-(\d{2})$/.exec(fecha ?? '');

    if (!partes) return '';

    const [, anio, mes, dia] = partes.map(Number);
    const real = new Date(anio, mes - 1, dia);

    if (real.getFullYear() !== anio || real.getMonth() !== mes - 1 || real.getDate() !== dia) return '';

    const iso = fechaISO(real);
    let texto;

    if (iso === fechaEnDias(0, hoy)) texto = 'Hoy';
    else if (iso === fechaEnDias(1, hoy)) texto = 'Mañana';
    else texto = `${dia} ${MESES[mes - 1]}${anio !== hoy.getFullYear() ? ` ${anio}` : ''}`;

    return /^\d{2}:\d{2}$/.test(hora ?? '') ? `${texto} ${hora}` : texto;
}

/** Cada chip solo aplica a ciertos tipos. */
export function chipVisible(paraTipos, tipo) {
    return paraTipos.split(' ').includes(tipo);
}

/** Resumen del chip "Más detalles" de la tarea: la prioridad si no es la media y el contexto. Vacío si no hay nada que destacar. */
export function resumenTarea({ prioridad = '', etiquetaPrioridad = '', contexto = '' } = {}) {
    const partes = [];

    if (prioridad !== '' && prioridad !== 'media' && etiquetaPrioridad !== '') partes.push(etiquetaPrioridad);
    if (contexto.trim() !== '') partes.push(contexto.trim());

    return partes.join(' · ');
}

/** El color de la tarjeta se guarda en las notas y en las tareas, no en los recordatorios. */
export function tipoConColor(tipo) {
    return tipo === 'nota' || tipo === 'tarea';
}

export const TIPOS = ['nota', 'tarea', 'recordatorio'];

/** Tipo guardado en el navegador si es válido; si no, "nota". */
export function tipoValido(valor) {
    return TIPOS.includes(valor) ? valor : 'nota';
}
