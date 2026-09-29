/*
 * Lógica pura (sin DOM ni red) de la tarjeta Pomodoro de Hoy, para poder probarla con Node.
 * El tiempo y las fases los calcula el motor (pomodoro-logica.js); aquí solo se decide qué mostrar.
 */
import { DESCANSO, FOCO, LIBRE } from './pomodoro-logica.js';

export const MODOS = ['foco', 'descanso', 'largo'];
export const PUNTOS = 4;
export const RADIO_ANILLO = 77;
export const CIRCUNFERENCIA = 2 * Math.PI * RADIO_ANILLO;

/**
 * Tamaño del reloj según cuántos caracteres tiene ("25:00" = 5, "180:00" = 6, "2:59:59" = 7, "12:34:56" = 8):
 * cuanto más largo el texto, más chico, para que siempre quepa dentro del anillo.
 */
export function tamanoReloj(texto) {
    const largo = String(texto).length;

    if (largo <= 5) return 'normal';
    if (largo === 6) return 'medio';
    if (largo === 7) return 'largo';

    return 'extra';
}

/** Modo del control segmentado que corresponde a la fase en curso (el tiempo libre cuenta como descanso). */
export function modoDeEstado(estado) {
    if (estado.fase === FOCO) return 'foco';

    return estado.descansoLargo ? 'largo' : 'descanso';
}

/** Segundos planificados de un modo según la configuración guardada. */
export function segundosDeModo(config, modo) {
    if (modo === 'descanso') return config.descanso;
    if (modo === 'largo') return config.largo;

    return config.foco;
}

/** Desplazamiento del trazo del anillo: lleno al empezar y se vacía a medida que pasa el tiempo. */
export function desplazamientoAnillo(progreso) {
    if (progreso === null || progreso === undefined || Number.isNaN(progreso)) return 0;

    return CIRCUNFERENCIA * Math.min(1, Math.max(0, progreso));
}

/** Cuántos de los puntos de pomodoros completados hoy van llenos. */
export const puntosLlenos = (total, puntos = PUNTOS) => Math.max(0, Math.min(puntos, total));

/** Etiqueta chica bajo el reloj. */
export function etiquetaFase(estado, pausado, modoElegido) {
    if (!estado) return { foco: 'ENFOQUE', descanso: 'DESCANSO', largo: 'PAUSA LARGA' }[modoElegido] ?? 'LISTO';
    if (pausado) return 'EN PAUSA';
    if (estado.fase === LIBRE) return 'TIEMPO LIBRE';
    if (estado.fase === DESCANSO) return estado.descansoLargo ? 'PAUSA LARGA' : 'DESCANSO';

    return 'ENFOCADO';
}

/**
 * Cuenta los pomodoros terminados desde que se abrió la página, para sumarlos al total de hoy
 * que vino de la base. Recibe el contador anterior y el estado actual del motor.
 *
 * @param {{ sesionId: number|null, completados: number, extra: number }} previo
 * @param {object|null} estado
 */
export function actualizarContador(previo, estado) {
    if (!estado) return { ...previo, sesionId: null, completados: 0 };

    // Sesión distinta de la que se venía siguiendo: todo lo que lleve hecho es nuevo.
    const mismaSesion = estado.sesionId === previo.sesionId;
    const anteriores = mismaSesion ? previo.completados : 0;

    return {
        sesionId: estado.sesionId,
        completados: estado.completados,
        extra: previo.extra + Math.max(0, estado.completados - anteriores),
    };
}

/** Contador inicial: si al abrir la página ya había una sesión, lo que lleva hecho ya está en el total de la base. */
export const contadorInicial = (estado) => ({
    sesionId: estado ? estado.sesionId : null,
    completados: estado ? estado.completados : 0,
    extra: 0,
});

/** Texto y estado del botón principal según la fase. */
export function botonPrincipal(estado, pausado) {
    if (!estado) return { texto: 'Iniciar', accion: 'iniciar' };
    if (estado.fase === LIBRE) return { texto: 'Siguiente foco', accion: 'siguiente-foco' };

    return pausado ? { texto: 'Reanudar', accion: 'reanudar' } : { texto: 'Pausar', accion: 'pausar' };
}
