/*
 * Modal de cuenta (layouts/_dialogo-cuenta.blade.php): iniciar sesión o crear cuenta sin salir de la pantalla.
 * Se envía con JSON (red.js); los errores de validación se muestran junto a cada campo y, si sale bien,
 * se recarga la página (el servidor deja el aviso "Cuenta creada" o "Iniciaste sesión").
 */
import { aviso } from './avisos.js';
import { pedirSeguro, primerMensaje } from './red.js';

const CLAVE_AVISO_INVITADO = 'focodiario.invitado.avisado';

export function activarCuenta() {
    const dialogo = document.getElementById('dialogo-cuenta');

    if (!dialogo) return;

    const pestanas = [...dialogo.querySelectorAll('[data-pestana]')];
    let origen = null;
    let pulsoEnFondo = false;

    const panel = (nombre) => document.getElementById(`cuenta-panel-${nombre}`);

    function limpiarErrores(formulario) {
        formulario.querySelectorAll('[data-error]').forEach((zona) => { zona.textContent = ''; });
        formulario.querySelectorAll('[aria-invalid]').forEach((campo) => campo.removeAttribute('aria-invalid'));
        formulario.querySelectorAll('.is-invalid').forEach((campo) => campo.classList.remove('is-invalid'));
        formulario.querySelector('[data-avisos]').textContent = '';
    }

    function mostrarPestana(nombre, { enfocar = true } = {}) {
        pestanas.forEach((pestana) => {
            const activa = pestana.dataset.pestana === nombre;

            pestana.setAttribute('aria-selected', activa ? 'true' : 'false');
            pestana.tabIndex = activa ? 0 : -1;
            panel(pestana.dataset.pestana).hidden = !activa;
        });

        if (enfocar) panel(nombre).querySelector('input:not([type="hidden"])')?.focus();
    }

    function abrir(nombre, desde = null) {
        origen = desde;
        dialogo.querySelectorAll('[data-form-cuenta]').forEach(limpiarErrores);

        if (!dialogo.open) dialogo.showModal();

        mostrarPestana(nombre);
    }

    // Pestañas: clic y flechas (patrón de tabs accesibles).
    pestanas.forEach((pestana, indice) => {
        pestana.addEventListener('click', () => mostrarPestana(pestana.dataset.pestana));
        pestana.addEventListener('keydown', (evento) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(evento.key)) return;

            const otra = pestanas[(indice + (evento.key === 'ArrowRight' ? 1 : -1) + pestanas.length) % pestanas.length];

            mostrarPestana(otra.dataset.pestana, { enfocar: false });
            otra.focus();
        });
    });

    // Enlace del pie ("¿No tenés cuenta? Crear cuenta"): cambia de pestaña sin ser una pestaña más.
    dialogo.querySelectorAll('[data-cambiar-pestana]').forEach((enlace) => {
        enlace.addEventListener('click', () => mostrarPestana(enlace.dataset.cambiarPestana));
    });

    // Ojo de la contraseña: alterna entre ocultarla y mostrarla.
    dialogo.querySelectorAll('[data-ver-clave]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const campo = boton.closest('.cuenta-campo').querySelector('input');
            const ver = campo.type === 'password';

            campo.type = ver ? 'text' : 'password';
            boton.setAttribute('aria-pressed', String(ver));
            boton.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
            boton.firstElementChild.className = `bi bi-${ver ? 'eye-slash' : 'eye'}`;
        });
    });

    document.addEventListener('click', (evento) => {
        const disparador = evento.target.closest('[data-abrir-cuenta]');

        if (!disparador || evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey) return;

        evento.preventDefault();
        abrir(disparador.dataset.abrirCuenta, disparador);
    });

    dialogo.addEventListener('mousedown', (evento) => { pulsoEnFondo = evento.target === dialogo; });
    dialogo.addEventListener('click', (evento) => {
        if (evento.target.closest('[data-cerrar-cuenta]') || (evento.target === dialogo && pulsoEnFondo)) dialogo.close();
    });
    dialogo.addEventListener('close', () => {
        if (origen?.isConnected) origen.focus();
    });

    dialogo.querySelectorAll('[data-form-cuenta]').forEach((formulario) => {
        const enviar = formulario.querySelector('[data-enviar]');
        const avisos = formulario.querySelector('[data-avisos]');

        formulario.addEventListener('submit', async (evento) => {
            evento.preventDefault();

            if (enviar.disabled) return;

            limpiarErrores(formulario);
            enviar.disabled = true;

            try {
                const respuesta = await pedirSeguro(formulario.action, {
                    method: 'POST',
                    body: JSON.stringify(Object.fromEntries(new FormData(formulario))),
                });
                const datos = await respuesta.json().catch(() => ({}));

                if (respuesta.ok) {
                    // Sin el ?cuenta=… para que el modal no se vuelva a abrir.
                    const url = new URL(window.location.href);

                    url.searchParams.delete('cuenta');
                    window.location.replace(url);

                    return;
                }

                if (respuesta.status === 422) {
                    let primero = null;

                    Object.entries(datos.errors ?? {}).forEach(([nombre, mensajes]) => {
                        const zona = formulario.querySelector(`[data-error="${nombre}"]`);
                        const campo = formulario.elements[nombre];

                        if (!zona) {
                            avisos.textContent = mensajes.join(' ');

                            return;
                        }

                        zona.textContent = mensajes.join(' ');
                        campo?.setAttribute('aria-invalid', 'true');
                        campo?.classList.add('is-invalid');
                        primero ??= campo;
                    });

                    primero?.focus();
                } else if (respuesta.status === 429) {
                    aviso.aviso('Demasiados intentos seguidos. Esperá un minuto y probá de nuevo.');
                } else {
                    aviso.error(primerMensaje(datos) ?? 'No se pudo completar. Probá de nuevo.');
                }
            } catch {
                aviso.error('No hay conexión con el servidor. Probá de nuevo.');
            }

            enviar.disabled = false;
        });
    });

    // Una sola vez por navegador: explicarle al invitado dónde quedan sus datos.
    if (!dialogo.dataset.abrirAlCargar && !yaSeAviso()) {
        aviso.info('Estás usando FocoDiario como invitado: lo que guardes queda solo en este navegador.', {
            detalle: 'Creá una cuenta para no perderlo y usarlo en otros equipos.',
            accion: { texto: 'Crear cuenta', alHacer: () => abrir('crear') },
            duracion: 15000,
        });
    }

    // ?cuenta=entrar / ?cuenta=crear (p. ej. los enlaces viejos a /entrar): se abre al cargar y se limpia la URL.
    if (dialogo.dataset.abrirAlCargar) {
        abrir(dialogo.dataset.abrirAlCargar);

        const url = new URL(window.location.href);

        url.searchParams.delete('cuenta');
        window.history.replaceState(null, '', url);
    }
}

function yaSeAviso() {
    try {
        if (window.localStorage.getItem(CLAVE_AVISO_INVITADO)) return true;

        window.localStorage.setItem(CLAVE_AVISO_INVITADO, '1');
    } catch {
        // Sin almacenamiento: el aviso puede repetirse, no es grave.
    }

    return false;
}
