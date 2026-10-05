// Modales (<dialog> nativo) de Tareas: nueva/editar tarea y nueva/editar/eliminar columna.
// Mejora progresiva: sin JS, los enlaces llevan a las páginas de siempre y los formularios de columna
// viven dentro de <noscript>. Con JS, los botones [data-requiere-js] se muestran y abren estos modales.
//
// Cada modal es un <dialog data-modal> con un <form data-form-modal>. Se envía con fetch (JSON):
//  - 422: los errores se pintan dentro del modal, junto a cada campo, sin perder lo escrito.
//  - éxito: se recarga la página; el servidor deja el aviso "Tarea creada." en la sesión.
import { aviso } from './avisos.js';
import { pedirSeguro } from './red.js';

const dialogos = document.querySelectorAll('dialog[data-modal]');

if (dialogos.length > 0) {
    document.querySelectorAll('[data-requiere-js]').forEach((elemento) => { elemento.hidden = false; });

    const FOCALIZABLES = 'a[href], button:not(:disabled), input:not(:disabled):not([type="hidden"]), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])';
    const abierto = new WeakMap(); // dialogo -> { opener, retorno, url, metodo }

    // ---------- Comportamiento común ----------

    const enfocarInicial = (dialogo, campo) => {
        const objetivo = campo ?? dialogo.querySelector('input:not([type="hidden"]), select, textarea');
        objetivo?.focus();

        if (objetivo instanceof HTMLInputElement && objetivo.type === 'text') {
            objetivo.setSelectionRange(objetivo.value.length, objetivo.value.length);
        }
    };

    const abrirDialogo = (dialogo, opener, estado) => {
        // El menú de la columna se cierra al abrir el modal: el foco vuelve a su botón de opciones.
        const menu = opener?.closest('details');
        const retorno = menu ? menu.querySelector(':scope > summary') : opener;

        if (menu) {
            menu.open = false;
        }

        abierto.set(dialogo, { opener, retorno, ...estado });
        dialogo.showModal();
    };

    const limpiarErrores = (dialogo) => {
        dialogo.querySelectorAll('[data-error]').forEach((zona) => { zona.textContent = ''; });
        dialogo.querySelectorAll('[aria-invalid]').forEach((campo) => {
            campo.removeAttribute('aria-invalid');
            campo.classList.remove('is-invalid');
        });
        const avisos = dialogo.querySelector('[data-avisos]');

        if (avisos) {
            avisos.replaceChildren();
        }
    };

    const mostrarAvisos = (dialogo, mensajes) => {
        const avisos = dialogo.querySelector('[data-avisos]');
        avisos.replaceChildren(...mensajes.map((texto) => {
            const linea = document.createElement('div');
            linea.textContent = texto;

            return linea;
        }));
    };

    // Errores por campo (data-error="<nombre>"); los que no tienen campo van al aviso general.
    const mostrarErrores = (dialogo, errores) => {
        limpiarErrores(dialogo);
        let primero = null;
        const sueltos = [];

        Object.entries(errores).forEach(([nombre, mensajes]) => {
            const zona = dialogo.querySelector(`[data-error="${nombre}"]`);
            const campo = dialogo.querySelector(`[name="${nombre}"]`);

            if (!zona || !campo) {
                sueltos.push(...mensajes);

                return;
            }

            zona.textContent = mensajes.join(' ');
            campo.setAttribute('aria-invalid', 'true');
            campo.classList.add('is-invalid');
            primero ??= campo;
        });

        if (sueltos.length > 0) {
            mostrarAvisos(dialogo, sueltos);
        }

        primero?.focus();
    };

    const enviar = async (dialogo, formulario) => {
        const { url, metodo } = abierto.get(dialogo) ?? {};
        const boton = formulario.querySelector('[data-enviar]');

        if (!url || boton.disabled) {
            return;
        }

        limpiarErrores(dialogo);
        boton.disabled = true;

        try {
            const respuesta = await pedirSeguro(url, {
                method: metodo,
                headers: { 'X-Modal': '1' },
                body: JSON.stringify(Object.fromEntries(new FormData(formulario))),
            });

            if (respuesta.status === 422) {
                mostrarErrores(dialogo, (await respuesta.json()).errors ?? {});
                boton.disabled = false;

                return;
            }

            if (!respuesta.ok) {
                throw new Error(String(respuesta.status));
            }

            // Guardado: la página se recarga con el aviso del servidor; el botón queda apagado hasta entonces.
            window.location.reload();
        } catch {
            aviso.error('No se pudo guardar. Revisá tu conexión e intentá de nuevo.');
            boton.disabled = false;
        }
    };

    dialogos.forEach((dialogo) => {
        const formulario = dialogo.querySelector('[data-form-modal]');
        let pulsoEnFondo = false;

        // Botones de cerrar y Cancelar.
        dialogo.addEventListener('click', (evento) => {
            if (evento.target.closest('[data-cerrar-modal]')) {
                dialogo.close();

                return;
            }

            // Clic en el fondo: solo si empezó y terminó fuera de la tarjeta (así arrastrar para seleccionar texto no lo cierra).
            if (evento.target === dialogo && pulsoEnFondo) {
                dialogo.close();
            }
        });
        dialogo.addEventListener('mousedown', (evento) => { pulsoEnFondo = evento.target === dialogo; });

        // Foco atrapado: Tab y Mayús+Tab dan la vuelta dentro del modal.
        dialogo.addEventListener('keydown', (evento) => {
            if (evento.key !== 'Tab') {
                return;
            }

            const lista = [...dialogo.querySelectorAll(FOCALIZABLES)].filter((el) => el.offsetParent !== null);

            if (lista.length === 0) {
                return;
            }

            const primero = lista[0];
            const ultimo = lista[lista.length - 1];

            if (evento.shiftKey && document.activeElement === primero) {
                evento.preventDefault();
                ultimo.focus();
            } else if (!evento.shiftKey && document.activeElement === ultimo) {
                evento.preventDefault();
                primero.focus();
            }
        });

        // Al cerrar (Esc, botón o fondo): se limpia y el foco vuelve a quien abrió el modal.
        dialogo.addEventListener('close', () => {
            const { retorno } = abierto.get(dialogo) ?? {};
            limpiarErrores(dialogo);
            formulario.querySelector('[data-enviar]').disabled = false;

            if (retorno?.isConnected) {
                retorno.focus();
            }
        });

        formulario.addEventListener('submit', (evento) => {
            evento.preventDefault();
            enviar(dialogo, formulario);
        });
    });

    // ---------- Tarea ----------

    const dialogoTarea = document.getElementById('dialogo-tarea');

    if (dialogoTarea) {
        const formulario = dialogoTarea.querySelector('form');
        const titulo = dialogoTarea.querySelector('[data-titulo-dialogo]');
        const enviarBtn = dialogoTarea.querySelector('[data-enviar]');
        const campos = ['titulo', 'descripcion', 'contexto_id', 'fecha_limite', 'prioridad', 'columna_id', 'color'];

        const abrirTarea = (opener) => {
            const editar = opener.dataset.abrirTarea === 'editar';
            const datos = editar ? JSON.parse(opener.dataset.tarea) : {};

            formulario.reset();
            limpiarErrores(dialogoTarea);

            // Si la tarjeta se arrastró a otra columna, manda la columna que muestra el tablero.
            const columnaEnTablero = opener.closest('.tablero-columna[data-columna]')?.dataset.columna;

            if (editar && columnaEnTablero) {
                datos.columna_id = Number(columnaEnTablero);
            }

            if (!editar) {
                datos.columna_id = Number(opener.dataset.columna || dialogoTarea.dataset.columnaInicial);
            }

            campos.forEach((nombre) => {
                if (datos[nombre] !== undefined && datos[nombre] !== null) {
                    formulario.elements[nombre].value = datos[nombre];
                }
            });

            titulo.textContent = editar ? 'Editar tarea' : 'Nueva tarea';
            enviarBtn.textContent = editar ? enviarBtn.dataset.textoEditar : enviarBtn.dataset.textoCrear;
            abrirDialogo(dialogoTarea, opener, editar
                ? { url: datos.url, metodo: 'PATCH' }
                : { url: dialogoTarea.dataset.urlCrear, metodo: 'POST' });
            enfocarInicial(dialogoTarea, formulario.elements.titulo);
        };

        document.addEventListener('click', (evento) => {
            const opener = evento.target.closest('[data-abrir-tarea]');

            // Ctrl/Cmd/Mayús+clic o botón central: se deja el enlace normal (abre la página en otra pestaña).
            if (!opener || evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) {
                return;
            }

            evento.preventDefault();
            abrirTarea(opener);
        });
    }

    // ---------- Recordatorio ----------

    const dialogoRecordatorio = document.getElementById('dialogo-recordatorio');

    if (dialogoRecordatorio) {
        const formulario = dialogoRecordatorio.querySelector('form');
        const titulo = dialogoRecordatorio.querySelector('[data-titulo-dialogo]');
        const enviarBtn = dialogoRecordatorio.querySelector('[data-enviar]');
        const campos = ['mensaje', 'descripcion', 'recordar_en', 'tarea_id'];

        const abrirRecordatorio = (opener) => {
            const editar = opener.dataset.abrirRecordatorio === 'editar';
            const datos = editar ? JSON.parse(opener.dataset.recordatorio) : {};

            formulario.reset();
            limpiarErrores(dialogoRecordatorio);

            campos.forEach((nombre) => {
                if (datos[nombre] !== undefined && datos[nombre] !== null) {
                    formulario.elements[nombre].value = datos[nombre];
                }
            });

            titulo.textContent = editar ? 'Editar recordatorio' : 'Nuevo recordatorio';
            enviarBtn.textContent = editar ? enviarBtn.dataset.textoEditar : enviarBtn.dataset.textoCrear;
            abrirDialogo(dialogoRecordatorio, opener, editar
                ? { url: datos.url, metodo: 'PATCH' }
                : { url: dialogoRecordatorio.dataset.urlCrear, metodo: 'POST' });
            enfocarInicial(dialogoRecordatorio, formulario.elements.mensaje);
        };

        document.addEventListener('click', (evento) => {
            const opener = evento.target.closest('[data-abrir-recordatorio]');

            if (!opener || evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) {
                return;
            }

            evento.preventDefault();
            abrirRecordatorio(opener);
        });
    }

    // ---------- Columna ----------

    const dialogoColumna = document.getElementById('dialogo-columna');
    const dialogoEliminar = document.getElementById('dialogo-columna-eliminar');

    const abrirColumna = (opener) => {
        const accion = opener.dataset.abrirColumna;
        const datos = opener.dataset.columnaDatos ? JSON.parse(opener.dataset.columnaDatos) : null;

        if (accion === 'eliminar' && dialogoEliminar) {
            const formulario = dialogoEliminar.querySelector('form');
            const select = formulario.elements.reasignar_a;
            const bloque = dialogoEliminar.querySelector('[data-bloque-destino]');
            const texto = dialogoEliminar.querySelector('[data-texto-eliminar]');
            const cantidad = Number(datos.tareas);

            limpiarErrores(dialogoEliminar);
            select.replaceChildren(...datos.otras.map((otra) => new Option(otra.nombre, otra.id)));
            // Sin tareas no hay nada que reasignar: el campo no se muestra ni se envía.
            bloque.hidden = cantidad === 0;
            select.disabled = cantidad === 0;
            texto.textContent = cantidad === 0
                ? `¿Eliminar la columna "${datos.nombre}"? Está vacía.`
                : `¿Eliminar la columna "${datos.nombre}"? Tiene ${cantidad} ${cantidad === 1 ? 'tarea' : 'tareas'}; elegí a qué columna pasan.`;
            abrirDialogo(dialogoEliminar, opener, { url: datos.urlEliminar, metodo: 'DELETE' });
            enfocarInicial(dialogoEliminar, cantidad === 0 ? dialogoEliminar.querySelector('[data-enviar]') : select);

            return;
        }

        if (!dialogoColumna) {
            return;
        }

        const formulario = dialogoColumna.querySelector('form');
        const editar = accion === 'editar';
        const ayuda = dialogoColumna.querySelector('[data-ayuda-categoria]');
        const enviarBtn = dialogoColumna.querySelector('[data-enviar]');

        formulario.reset();
        limpiarErrores(dialogoColumna);
        dialogoColumna.querySelector('[data-titulo-dialogo]').textContent = editar ? 'Editar columna' : 'Nueva columna';
        enviarBtn.textContent = editar ? enviarBtn.dataset.textoEditar : enviarBtn.dataset.textoCrear;

        formulario.elements.nombre.value = editar ? datos.nombre : '';
        formulario.elements.categoria.value = editar ? datos.categoria : 'en_progreso';
        // La única columna de su tipo (pendiente o completada) no puede cambiar de tipo.
        const fija = editar && datos.obligatoria;
        formulario.elements.categoria.disabled = fija;
        ayuda.textContent = fija
            ? `Es la única columna de tipo ${datos.categoriaEtiqueta}: el tablero necesita al menos una, por eso no se puede cambiar.`
            : 'Las tareas que caigan en esta columna toman ese estado.';

        abrirDialogo(dialogoColumna, opener, editar
            ? { url: datos.urlEditar, metodo: 'PATCH' }
            : { url: dialogoColumna.dataset.urlCrear, metodo: 'POST' });
        enfocarInicial(dialogoColumna, formulario.elements.nombre);
    };

    document.addEventListener('click', (evento) => {
        const opener = evento.target.closest('[data-abrir-columna]');

        if (opener) {
            evento.preventDefault();
            abrirColumna(opener);
        }
    });
}
