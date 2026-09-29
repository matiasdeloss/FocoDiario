/* Lógica pura de las listas de Hoy (tareas y recordatorios): sin DOM, para poder probarla con Node. */

/** Texto de la insignia de tareas abiertas (igual que App\Support\TextoPendientes). */
export function textoPendientes(pendientes) {
    if (pendientes <= 0) return 'Todo al día';

    return pendientes === 1 ? '1 pendiente' : `${pendientes} pendientes`;
}

/** Lee aria-checked ("true"/"false") o un booleano. */
export const estaMarcado = (valor) => valor === true || valor === 'true';

/** Una fila se puede alternar solo si no tiene una petición en curso. */
export const puedeAlternar = (ocupada) => !ocupada;

/**
 * Estado final de una fila tras la respuesta: manda lo que devolvió el servidor;
 * si no dijo nada, se mantiene lo pedido cuando salió bien y se vuelve al previo cuando falló.
 */
export function estadoFinal({ previo, pedido, ok, servidor }) {
    if (typeof servidor === 'boolean') return servidor;

    return ok ? pedido : previo;
}

/** El servidor de tareas informa el estado como texto: "completada" es marcada. Sin estado, undefined. */
export const marcadoDeTarea = (estado) => (typeof estado === 'string' ? estado === 'completada' : undefined);

/** Cantidad de pendientes: la del servidor si es un número válido; si no, la local (nunca negativa). */
export function pendientesFinales(servidor, local) {
    return Number.isInteger(servidor) && servidor >= 0 ? servidor : Math.max(0, local);
}

/**
 * Ejecuta las tareas asíncronas de a una, en orden de llegada, así las respuestas del servidor
 * no se pisan entre sí. A cada tarea le dice si es la última de la cola (solo esa redibuja la lista).
 */
export function crearSerie() {
    let cola = Promise.resolve();
    let enCurso = 0;

    return {
        agregar(tarea) {
            enCurso += 1;

            const correr = async () => {
                try {
                    return await tarea({ esUltima: () => enCurso === 1 });
                } finally {
                    enCurso -= 1;
                }
            };
            const resultado = cola.then(correr);

            cola = resultado.catch(() => {});

            return resultado;
        },
        get pendientes() { return enCurso; },
    };
}
