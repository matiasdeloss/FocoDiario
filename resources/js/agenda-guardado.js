/*
 * Autoguardado de la Agenda. Junta los cambios de cada caja (o de la hoja entera) y los manda de una vez
 * cuando se deja de escribir; si falla, reintenta con esperas crecientes y, si no se puede, avisa.
 * No toca el DOM ni la red: recibe "enviar" y "alEstado", así se prueba con Node (tests/js/agenda-guardado.test.mjs).
 *
 * Estados que informa: 'pendiente' (hay cambios esperando), 'guardando', 'guardado', 'reintentando' y 'error'.
 */
import { esperaDeReintento } from './agenda-logica.js';

export function crearGuardador({ enviar, alEstado = () => {}, espera = 700, esperasReintento = [1000, 3000, 8000], temporizador = globalThis }) {
    /** clave -> cambios juntados que todavía no se mandaron */
    const pendientes = new Map();
    /** clave -> temporizador del debounce */
    const esperando = new Map();
    /** claves que se están enviando ahora (se envía de a una por clave para no desordenar los cambios) */
    const enVuelo = new Set();
    /** clave -> cambios que fallaron definitivamente */
    const fallidos = new Map();
    /** clave -> temporizador de reintento */
    const reintentos = new Map();

    let ultimoEstado = 'guardado';

    const informar = (estado, detalle = null) => {
        ultimoEstado = estado;
        alEstado(estado, detalle);
    };

    const hayTrabajo = () => pendientes.size > 0 || enVuelo.size > 0 || reintentos.size > 0;

    async function enviarClave(clave, intento = 0) {
        if (enVuelo.has(clave) || !pendientes.has(clave)) {
            return;
        }

        const cambios = pendientes.get(clave);
        pendientes.delete(clave);
        enVuelo.add(clave);
        informar('guardando');

        try {
            await enviar(clave, cambios);
            enVuelo.delete(clave);
            fallidos.delete(clave);
        } catch (error) {
            enVuelo.delete(clave);
            // Lo que se escribió mientras tanto es más nuevo y pisa lo que falló.
            pendientes.set(clave, { ...cambios, ...(pendientes.get(clave) ?? {}) });

            const demora = error?.reintentable === false ? null : esperaDeReintento(intento, esperasReintento);

            if (demora === null) {
                fallidos.set(clave, pendientes.get(clave));
                pendientes.delete(clave);
                informar('error', error?.mensaje ?? 'No se pudo guardar.');

                return;
            }

            informar('reintentando', error?.mensaje ?? null);
            reintentos.set(clave, temporizador.setTimeout(() => {
                reintentos.delete(clave);
                enviarClave(clave, intento + 1);
            }, demora));

            return;
        }

        if (pendientes.has(clave)) {
            await enviarClave(clave);

            return;
        }

        if (!hayTrabajo()) {
            informar(fallidos.size > 0 ? 'error' : 'guardado');
        }
    }

    return {
        /** Anota cambios de una clave y los manda cuando pasa "espera" ms sin nuevos cambios. */
        programar(clave, cambios) {
            pendientes.set(clave, { ...(pendientes.get(clave) ?? {}), ...cambios });
            fallidos.delete(clave);
            temporizador.clearTimeout(esperando.get(clave));
            esperando.set(clave, temporizador.setTimeout(() => {
                esperando.delete(clave);
                enviarClave(clave);
            }, espera));
            informar('pendiente');
        },

        /** Manda ya todo lo que está esperando (por ejemplo, antes de salir de la página). */
        async vaciar() {
            const claves = [...pendientes.keys()];

            for (const clave of claves) {
                temporizador.clearTimeout(esperando.get(clave));
                esperando.delete(clave);
                temporizador.clearTimeout(reintentos.get(clave));
                reintentos.delete(clave);
            }

            await Promise.all(claves.map((clave) => enviarClave(clave)));
        },

        /** Vuelve a intentar lo que falló. */
        async reintentar() {
            for (const [clave, cambios] of fallidos) {
                pendientes.set(clave, { ...cambios, ...(pendientes.get(clave) ?? {}) });
            }

            fallidos.clear();
            await this.vaciar();
        },

        /** Olvida lo pendiente de una clave (por ejemplo, de una caja que se acaba de borrar). */
        descartar(clave) {
            temporizador.clearTimeout(esperando.get(clave));
            temporizador.clearTimeout(reintentos.get(clave));
            esperando.delete(clave);
            reintentos.delete(clave);
            pendientes.delete(clave);
            fallidos.delete(clave);

            if (!hayTrabajo()) {
                informar(fallidos.size > 0 ? 'error' : 'guardado');
            }
        },

        hayCambiosSinGuardar: () => hayTrabajo() || fallidos.size > 0,
        estado: () => ultimoEstado,
    };
}
