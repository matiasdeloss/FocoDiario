/*
 * Pedidos JSON de la Agenda (sobre red.js: CSRF y renovación del token). Los errores llevan "mensaje" (para mostrar)
 * y "reintentable" (si tiene sentido volver a intentar: fallas de red y del servidor sí; validación y sesión vencida no).
 */
import { pedirSeguro, primerMensaje } from './red.js';

export async function pedirJson(metodo, url, cuerpo, opciones = {}) {
    let respuesta;

    try {
        respuesta = await pedirSeguro(url, {
            method: metodo,
            body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo),
            keepalive: Boolean(opciones.keepalive),
        });
    } catch {
        throw Object.assign(new Error('sin conexión'), { reintentable: true, mensaje: 'No hay conexión con el servidor.' });
    }

    let datos = null;

    try {
        datos = await respuesta.json();
    } catch {
        datos = null;
    }

    if (!respuesta.ok) {
        const mensaje = respuesta.status === 419
            ? 'La sesión venció. Recargá la página para seguir guardando.'
            : primerMensaje(datos) ?? 'No se pudo guardar.';

        throw Object.assign(new Error(mensaje), { reintentable: respuesta.status >= 500, mensaje, estado: respuesta.status });
    }

    return datos;
}

/** Función "enviar" del guardador: la clave es "METODO url" y los cambios son el cuerpo del pedido. */
export function enviarCambios(clave, cambios) {
    const separador = clave.indexOf(' ');

    return pedirJson(clave.slice(0, separador), clave.slice(separador + 1), cambios);
}
