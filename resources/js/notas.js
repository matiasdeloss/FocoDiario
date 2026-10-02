// Notas: modal (<dialog> nativo) de crear/editar, vista cuadrícula/lista y estado vacío al borrar.
// Mejora progresiva: sin JS, los enlaces llevan a las páginas de crear/editar y los filtros son enlaces.
import { aviso } from './avisos.js';
import { pedirSeguro } from './red.js';

const lista = document.getElementById('lista-notas');
const contenedor = document.getElementById('notas');
const dialogo = document.getElementById('dialogo-nota');

const FOCALIZABLES = 'a[href], button:not(:disabled), input:not(:disabled):not([type="hidden"]), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])';

// ---------- Vista cuadrícula / lista (se recuerda en el navegador) ----------
const vistas = document.querySelector('[data-vistas]');

if (vistas && contenedor) {
    vistas.hidden = false;
    const aplicar = (vista) => {
        contenedor.dataset.vista = vista;
        vistas.querySelectorAll('[data-vista]').forEach((boton) => {
            boton.setAttribute('aria-pressed', String(boton.dataset.vista === vista));
        });
    };

    let guardada = 'cuadricula';

    try {
        guardada = localStorage.getItem('notas-vista') === 'lista' ? 'lista' : 'cuadricula';
    } catch { /* sin almacenamiento */ }

    aplicar(guardada);
    vistas.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-vista]');

        if (!boton) {
            return;
        }

        aplicar(boton.dataset.vista);

        try {
            localStorage.setItem('notas-vista', boton.dataset.vista);
        } catch { /* sin almacenamiento */ }
    });
}

// ---------- Estado vacío y conteo al borrar (HTMX) ----------
const vacio = document.querySelector('[data-vacio]');
const conteo = document.querySelector('[data-conteo]');

document.addEventListener('htmx:afterSettle', () => {
    if (!lista) {
        return;
    }

    const total = lista.querySelectorAll('.nota-item').length;

    if (conteo) {
        conteo.textContent = String(total);
    }

    if (vacio) {
        vacio.hidden = total > 0;
    }
});

// ---------- Modal ----------
if (dialogo) {
    const formulario = dialogo.querySelector('[data-form-nota]');
    const titulo = dialogo.querySelector('[data-titulo-dialogo]');
    const enviarBtn = dialogo.querySelector('[data-enviar]');
    const avisos = dialogo.querySelector('[data-avisos]');
    let estado = { url: dialogo.dataset.urlCrear, metodo: 'POST', retorno: null };
    let pulsoEnFondo = false;

    const limpiarErrores = () => {
        dialogo.querySelectorAll('[data-error]').forEach((zona) => { zona.textContent = ''; });
        dialogo.querySelectorAll('[aria-invalid]').forEach((campo) => {
            campo.removeAttribute('aria-invalid');
            campo.classList.remove('is-invalid');
        });
        avisos.textContent = '';
    };

    const fijarValores = (datos) => {
        formulario.reset();
        const el = formulario.elements;
        el.titulo.value = datos.titulo ?? '';
        el.contenido.value = datos.contenido ?? '';
        el.contexto_id.value = datos.contexto_id ?? '';
        el.fecha.value = datos.fecha ?? '';
        el.fijada[1].checked = Boolean(datos.fijada);
        const color = datos.color ?? '';
        [...formulario.querySelectorAll('input[name="color"]')].forEach((radio) => { radio.checked = radio.value === color; });
    };

    const abrir = (opener, datos, nuevoEstado) => {
        limpiarErrores();
        fijarValores(datos);
        titulo.textContent = nuevoEstado.metodo === 'POST' ? 'Nueva nota' : 'Editar nota';
        estado = { ...nuevoEstado, retorno: opener };
        dialogo.showModal();
        const campo = formulario.elements.titulo;
        campo.focus();
        campo.setSelectionRange(campo.value.length, campo.value.length);
    };

    document.addEventListener('click', (evento) => {
        const opener = evento.target.closest('[data-abrir-nota]');

        // Ctrl/Cmd/Mayús+clic o botón central: se deja el enlace normal.
        if (!opener || evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) {
            return;
        }

        evento.preventDefault();

        if (opener.dataset.abrirNota === 'editar') {
            const datos = JSON.parse(opener.dataset.nota);
            abrir(opener, datos, { url: datos.url, metodo: 'PUT' });
        } else {
            abrir(opener, { contexto_id: opener.dataset.contexto || '' }, { url: dialogo.dataset.urlCrear, metodo: 'POST' });
        }
    });

    dialogo.addEventListener('click', (evento) => {
        if (evento.target.closest('[data-cerrar-modal]') || (evento.target === dialogo && pulsoEnFondo)) {
            dialogo.close();
        }
    });
    dialogo.addEventListener('mousedown', (evento) => { pulsoEnFondo = evento.target === dialogo; });

    // Foco atrapado dentro del modal.
    dialogo.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Tab') {
            return;
        }

        const elementos = [...dialogo.querySelectorAll(FOCALIZABLES)].filter((el) => el.offsetParent !== null || el.type === 'radio');
        const primero = elementos[0];
        const ultimo = elementos[elementos.length - 1];

        if (evento.shiftKey && document.activeElement === primero) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    });

    dialogo.addEventListener('close', () => {
        limpiarErrores();
        enviarBtn.disabled = false;

        if (estado.retorno?.isConnected) {
            estado.retorno.focus();
        }
    });

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        if (enviarBtn.disabled) {
            return;
        }

        limpiarErrores();
        enviarBtn.disabled = true;

        try {
            const datos = Object.fromEntries(new FormData(formulario));
            const respuesta = await pedirSeguro(estado.url, {
                method: estado.metodo,
                body: JSON.stringify(datos),
            });

            if (respuesta.status === 422) {
                const errores = (await respuesta.json()).errors ?? {};
                let primero = null;
                const sueltos = [];

                Object.entries(errores).forEach(([nombre, mensajes]) => {
                    const zona = dialogo.querySelector(`[data-error="${nombre}"]`);
                    const campo = formulario.elements[nombre];

                    if (!zona) {
                        sueltos.push(...mensajes);

                        return;
                    }

                    zona.textContent = mensajes.join(' ');

                    if (campo instanceof Element) {
                        campo.setAttribute('aria-invalid', 'true');
                        campo.classList.add('is-invalid');
                        primero ??= campo;
                    }
                });

                avisos.textContent = sueltos.join(' ');
                primero?.focus();
                enviarBtn.disabled = false;

                return;
            }

            if (!respuesta.ok) {
                throw new Error(String(respuesta.status));
            }

            window.location.reload();
        } catch {
            aviso.error('No se pudo guardar. Revisá tu conexión e intentá de nuevo.');
            enviarBtn.disabled = false;
        }
    });
}

// ---------- Ver la nota completa ----------
// Clic en el título o el contenido (o en una zona libre de la tarjeta): modal de lectura con la nota sin recortar.
// Sin JS, el enlace lleva a la página de la nota. "Editar" pasa al modal de edición de siempre.
const dialogoVer = document.getElementById('dialogo-ver-nota');

if (dialogoVer) {
    const campo = (nombre) => dialogoVer.querySelector(`[data-ver="${nombre}"]`);
    const mostrarFila = (nombre, visible) => { dialogoVer.querySelector(`[data-fila="${nombre}"]`).hidden = !visible; };
    let tarjeta = null;
    let origen = null;
    let pulsoEnFondo = false;

    const ver = (enlace) => {
        const datos = JSON.parse(enlace.dataset.verNota);
        const contenido = (datos.contenido ?? '').trim();

        tarjeta = enlace.closest('.nota-item');
        origen = enlace;

        campo('titulo').textContent = datos.titulo || 'Nota';
        campo('contenido').textContent = contenido || 'Nota sin contenido';
        campo('contenido').classList.toggle('es-vacia', contenido === '');
        campo('destino').textContent = datos.destino ?? 'Bandeja de entrada';
        ['fecha', 'creada', 'editada'].forEach((nombre) => {
            campo(nombre).textContent = datos[nombre] ?? '';
            mostrarFila(nombre, Boolean(datos[nombre]));
        });
        mostrarFila('fijada', Boolean(datos.fijada));

        dialogoVer.classList.toggle('con-color', Boolean(datos.fondo));
        dialogoVer.style.setProperty('--nota-fondo', datos.fondo ?? '');
        dialogoVer.style.setProperty('--nota-marca', datos.marca ?? '');

        dialogoVer.showModal();
        campo('contenido').scrollTop = 0;
        dialogoVer.querySelector('[data-cerrar-ver]').focus();
    };

    document.addEventListener('click', (evento) => {
        // Ctrl/Cmd/Mayús+clic o botón central: se deja el enlace normal.
        if (evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;

        const enlace = evento.target.closest('[data-ver-nota]');

        if (enlace) {
            evento.preventDefault();
            ver(enlace);

            return;
        }

        // Zona libre de la tarjeta: no sus botones, el selector de destino ni texto que se esté seleccionando.
        const libre = evento.target.closest('.nota-item');

        if (libre && !evento.target.closest('a, button, select, input, label, form') && !window.getSelection()?.toString()) {
            const enlaceDeLaTarjeta = libre.querySelector('[data-ver-nota]');

            if (enlaceDeLaTarjeta) ver(enlaceDeLaTarjeta);
        }
    });

    dialogoVer.addEventListener('mousedown', (evento) => { pulsoEnFondo = evento.target === dialogoVer; });
    dialogoVer.addEventListener('click', (evento) => {
        if (evento.target.closest('[data-cerrar-ver]') || (evento.target === dialogoVer && pulsoEnFondo)) {
            dialogoVer.close();
        }
    });

    dialogoVer.querySelector('[data-editar-desde-ver]').addEventListener('click', () => {
        const editar = tarjeta?.querySelector('[data-abrir-nota="editar"]');

        // El foco lo toma el modal de edición (y al cerrarlo vuelve al lápiz de la tarjeta).
        origen = null;
        dialogoVer.close();
        editar?.click();
    });

    dialogoVer.addEventListener('close', () => {
        if (origen?.isConnected) origen.focus();
    });
}
