/*
 * Único punto de salida de los pedidos al servidor (fetch).
 *  - Agrega el token CSRF y pide JSON.
 *  - 419 (el token venció porque la página quedó abierta mucho tiempo): pide un token nuevo y reintenta una vez.
 *  - 401 (ya no hay sesión): lleva a la pantalla de entrada.
 * Las URL de renovación y de entrada vienen del layout (<meta name="url-csrf"> y <meta name="url-login">).
 */

const meta = (nombre) => document.querySelector(`meta[name="${nombre}"]`);

export const token = () => meta('csrf-token')?.content ?? '';

export const cabecerasJson = () => ({
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-CSRF-TOKEN': token(),
    'X-Requested-With': 'XMLHttpRequest',
});

export function irAEntrar() {
    const url = meta('url-login')?.content;

    if (url) window.location.assign(url);
}

let renovando = null;

/** Pide un token CSRF nuevo y lo deja en el <meta>. Devuelve true si lo consiguió. Varios pedidos simultáneos comparten uno solo. */
export function renovarToken() {
    const url = meta('url-csrf')?.content;

    if (!url) return Promise.resolve(false);

    renovando ??= (async () => {
        try {
            const respuesta = await fetch(url, { headers: { Accept: 'application/json' } });

            if (respuesta.status === 401) {
                irAEntrar();

                return false;
            }

            if (!respuesta.ok) return false;

            const { token: nuevo } = await respuesta.json();

            if (typeof nuevo !== 'string' || nuevo === '') return false;

            meta('csrf-token')?.setAttribute('content', nuevo);

            return true;
        } catch {
            return false;
        } finally {
            renovando = null;
        }
    })();

    return renovando;
}

/**
 * fetch con CSRF, renovación ante 419 y salida ante 401. Las cabeceras propias (p. ej. X-Modal) se suman a las comunes.
 * Si no hay red, lanza el error de fetch como siempre.
 */
export async function pedirSeguro(url, opciones = {}) {
    const enviar = () => fetch(url, { ...opciones, headers: { ...cabecerasJson(), ...(opciones.headers ?? {}) } });

    let respuesta = await enviar();

    if (respuesta.status === 419 && await renovarToken()) {
        respuesta = await enviar();
    }

    if (respuesta.status === 401) irAEntrar();

    return respuesta;
}

/** Primer mensaje de error de una respuesta de Laravel (validación o message). */
export function primerMensaje(datos) {
    const errores = datos?.errors ? Object.values(datos.errors).flat() : [];

    return errores[0] ?? datos?.message ?? null;
}
