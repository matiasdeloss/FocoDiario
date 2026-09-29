/*
 * Pedidos JSON de la Agenda (fetch con el token CSRF). Los errores llevan "mensaje" (para mostrar)
 * y "reintentable" (si tiene sentido volver a intentar: fallas de red y del servidor sí; validación y sesión vencida no).
 */

function token() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function primerMensaje(datos) {
    const errores = datos?.errors ? Object.values(datos.errors).flat() : [];

    return errores[0] ?? datos?.message ?? null;
}

export async function pedirJson(metodo, url, cuerpo, opciones = {}) {
    let respuesta;

    try {
        respuesta = await fetch(url, {
            method: metodo,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token(),
                'X-Requested-With': 'XMLHttpRequest',
            },
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
