import { Calendar } from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin, { Draggable } from '@fullcalendar/interaction';
import '../css/calendario.css';
import { aviso } from './avisos.js';
import { pedirSeguro, primerMensaje } from './red.js';
import { pintarCamposExtra } from './calendario-campos.js';
import { confirmar } from './confirmar.js';
import { tiposIniciales } from './calendario-filtros-logica.js';

const CLAVE_FILTROS = 'focodiario.calendario.tipos';
const CLAVE_VISTOS = 'focodiario.calendario.tipos-vistos'; // tipos que el usuario ya conoce: distingue "nunca lo vio" de "lo desmarcó"
const TIPOS = ['tarea', 'recordatorio', 'nota', 'sesion', 'planner'];
const TIPOS_TARJETA = ['tarea', 'recordatorio', 'nota'];
const ETIQUETAS = { tarea: 'Tarea', recordatorio: 'Recordatorio', nota: 'Nota', sesion: 'Sesión de estudio', planner: 'Planner semanal' };
const ARTICULOS = { tarea: 'la tarea', recordatorio: 'el recordatorio', nota: 'la nota', sesion: 'la sesión', planner: 'la caja del planner' };

const raiz = document.querySelector('[data-calendario]');

if (raiz) {
    iniciar(raiz);
}

function iniciar(raiz) {
    const datos = raiz.dataset;
    const elCalendario = document.getElementById('calendario');
    const panel = document.getElementById('panel-sin-fecha');
    const lista = document.getElementById('lista-sin-fecha');
    const vacio = document.getElementById('sin-fecha-vacio');
    const checks = [...document.querySelectorAll('[data-filtro-tipo]')];
    const esCelular = window.matchMedia('(max-width: 767.98px)');
    const esPantallaChica = window.matchMedia('(max-width: 575.98px)');
    let filtroPanel = '';

    /* ---------- Utilidades ---------- */
    const dosDigitos = (n) => String(n).padStart(2, '0');
    const fechaLocal = (d) => `${d.getFullYear()}-${dosDigitos(d.getMonth() + 1)}-${dosDigitos(d.getDate())}`;
    const horaLocal = (d) => `${dosDigitos(d.getHours())}:${dosDigitos(d.getMinutes())}:${dosDigitos(d.getSeconds())}`;
    const fechaHoraLocal = (d) => `${fechaLocal(d)}T${horaLocal(d)}`;
    const aDiaAMostrar = (iso) => iso.slice(0, 10).split('-').reverse().join('/');

    // Fallas al guardar o cargar: un aviso de error flotante (avisos.js).
    function mostrarAviso(mensaje) {
        aviso.error(mensaje);
    }

    function ajustarAlto(campo) {
        campo.style.height = 'auto';
        campo.style.height = `${campo.scrollHeight}px`;
    }

    /* ---------- Filtros por tipo del calendario (se recuerdan en localStorage) ---------- */
    function leerFiltros() {
        try {
            const guardado = JSON.parse(localStorage.getItem(CLAVE_FILTROS));
            const vistos = JSON.parse(localStorage.getItem(CLAVE_VISTOS));
            return tiposIniciales(guardado, vistos, TIPOS);
        } catch (e) { /* sin almacenamiento: se muestran todos */ }
        return [...TIPOS];
    }

    function guardarFiltros(tipos) {
        try {
            localStorage.setItem(CLAVE_FILTROS, JSON.stringify(tipos));
            localStorage.setItem(CLAVE_VISTOS, JSON.stringify(TIPOS));
        } catch (e) { /* ignorar */ }
    }

    function tiposActivos() {
        return checks.filter((c) => c.checked).map((c) => c.value);
    }

    const inicial = leerFiltros();
    checks.forEach((c) => { c.checked = inicial.includes(c.value); });

    /* ---------- Peticiones ---------- */
    async function pedir(url, metodo, cuerpo) {
        const opciones = { method: metodo };
        if (cuerpo !== undefined) {
            opciones.body = JSON.stringify(cuerpo);
        }

        let respuesta;
        try {
            respuesta = await pedirSeguro(url, opciones);
        } catch (e) {
            throw new Error('No hay conexión con el servidor. Probá de nuevo.');
        }

        let json = null;
        try { json = await respuesta.json(); } catch (e) { /* respuesta sin contenido */ }

        if (!respuesta.ok) {
            throw new Error(primerMensaje(json) || 'No se pudo guardar el cambio. Probá de nuevo.');
        }

        return json;
    }

    const enviar = (url, cuerpo) => pedir(url, 'PATCH', cuerpo);
    const urlDe = (plantilla, tipo, id) => plantilla.replace('__TIPO__', tipo).replace('__ID__', id);
    const urlTarjeta = (tipo, id) => urlDe(datos.urlTarjeta, tipo, id);
    const urlFecha = (tipo, id) => urlDe({
        tarea: datos.urlTarea,
        recordatorio: datos.urlRecordatorio,
        nota: datos.urlNota,
    }[tipo], tipo, id);

    /**
     * Vuelve un recordatorio ya avisado a "todavía no avisó". Mientras corre, el botón queda ocupado (sin doble clic).
     * Devuelve true si el servidor lo aceptó; avisa del resultado con un toast y refresca el calendario.
     */
    async function reactivarRecordatorio(id, boton) {
        boton.disabled = true;
        boton.setAttribute('aria-busy', 'true');
        try {
            await pedir(urlDe(datos.urlReactivar, 'recordatorio', id), 'PATCH');
            aviso.exito('Recordatorio marcado como no avisado.');
            calendario.refetchEvents();
            return true;
        } catch (error) {
            aviso.error('No se pudo actualizar el recordatorio. Probá de nuevo.');
            return false;
        } finally {
            boton.disabled = false;
            boton.removeAttribute('aria-busy');
        }
    }

    /** Asigna (valor) o quita (null) la fecha. Recordatorio: fecha y hora; tarea y nota: solo el día. */
    function ponerFecha(tipo, id, valor) {
        if (tipo === 'recordatorio') {
            const conSegundos = valor && valor.length === 16 ? `${valor}:00` : valor;
            return enviar(urlFecha(tipo, id), { recordar_en: conSegundos });
        }
        return enviar(urlFecha(tipo, id), { fecha: valor ? valor.slice(0, 10) : null });
    }

    /** Mueve una caja del planner: día y franja (sin hora_inicio queda de todo el día). */
    const moverPlanner = (id, cuerpo) => enviar(datos.urlPlanner.replace('__ID__', id), cuerpo);

    /**
     * Día y horas de un evento del planner. La hora de fin solo se envía si la caja ya tenía una o si se la estiró:
     * el fin de 1 h que dibuja el calendario en las cajas sin fin es solo visual y no se guarda al moverlas.
     * Un fin que cae otro día se recorta a las 23:59.
     */
    function datosPlanner(evento, estirada = false) {
        const fecha = fechaLocal(evento.start);
        if (evento.allDay) {
            return { fecha, hora_inicio: null, hora_fin: null };
        }
        const inicio = evento.start;
        const horaInicio = horaLocal(inicio).slice(0, 5);
        if (!estirada && !evento.extendedProps.tieneFin) {
            return { fecha, hora_inicio: horaInicio, hora_fin: null };
        }
        const fin = evento.end ?? new Date(inicio.getTime() + 3600000);
        const mismoDia = fechaLocal(fin) === fecha;
        return { fecha, hora_inicio: horaInicio, hora_fin: mismoDia ? horaLocal(fin).slice(0, 5) : '23:59' };
    }

    /** Fecha y hora de un recordatorio soltado en el calendario: 09:00 en el "todo el día" o la vista de mes. */
    const momentoRecordatorio = (inicio, todoElDia) => (todoElDia ? `${fechaLocal(inicio)}T09:00:00` : fechaHoraLocal(inicio));

    /* ---------- Panel "Por ubicar" ---------- */
    const tarjetasDelPanel = () => [...lista.querySelectorAll('[data-tarjeta]')];
    const buscarTarjeta = (tipo, id) => lista.querySelector(`[data-tarjeta][data-tipo="${tipo}"][data-id="${id}"]`);

    function actualizarPanel() {
        const visibles = tarjetasDelPanel().filter((li) => {
            const oculta = filtroPanel !== '' && li.dataset.tipo !== filtroPanel;
            li.hidden = oculta;
            return !oculta;
        });
        vacio.hidden = visibles.length > 0;
        document.querySelectorAll('[data-ver-mas]').forEach((boton) => {
            boton.hidden = boton.dataset.hayMas !== '1' || (filtroPanel !== '' && filtroPanel !== boton.dataset.verMas);
        });
    }

    document.querySelectorAll('[data-ver-mas]').forEach((boton) => {
        boton.dataset.hayMas = boton.hidden ? '0' : '1';
    });

    function insertarTarjeta(html, alFinal = false) {
        const plantilla = document.createElement('template');
        plantilla.innerHTML = html.trim();
        const nodos = [...plantilla.content.children];
        lista.insertAdjacentElement(alFinal ? 'beforeend' : 'afterbegin', nodos[0]);
        lista.querySelectorAll('textarea').forEach(ajustarAlto);
        actualizarPanel();
        return nodos[0];
    }

    function quitarDelPanel(tipo, id) {
        buscarTarjeta(tipo, id)?.remove();
        actualizarPanel();
    }

    document.querySelectorAll('[data-panel-filtro]').forEach((boton) => {
        boton.addEventListener('click', () => {
            filtroPanel = boton.dataset.panelFiltro;
            document.querySelectorAll('[data-panel-filtro]').forEach((b) => b.setAttribute('aria-pressed', String(b === boton)));
            actualizarPanel();
        });
    });

    lista.querySelectorAll('textarea').forEach(ajustarAlto);

    /* Indicador discreto de "guardado" */
    const temporizadores = new WeakMap();
    function indicar(elemento, texto, esError = false) {
        clearTimeout(temporizadores.get(elemento));
        elemento.textContent = texto;
        elemento.classList.toggle('tarj-guardado-error', esError);
        if (texto && !esError && texto !== 'Guardando…') {
            temporizadores.set(elemento, setTimeout(() => { elemento.textContent = ''; }, 2000));
        }
    }

    /**
     * Guarda un campo (título o comentario) de una tarjeta o del editor.
     * Devuelve true si el servidor lo aceptó.
     */
    async function guardarCampo({ tipo, id, campo, valor, indicador }) {
        indicar(indicador, 'Guardando…');
        try {
            await enviar(urlTarjeta(tipo, id), { [campo]: valor });
            indicar(indicador, 'Guardado');
            return true;
        } catch (error) {
            indicar(indicador, 'No se guardó', true);
            mostrarAviso(error.message);
            return false;
        }
    }

    async function alCambiarCampoDeTarjeta(campo) {
        const li = campo.closest('[data-tarjeta]');
        const nombre = campo.dataset.campo;
        const valor = campo.value.trim();

        if (valor === (campo.dataset.valor ?? '')) return;

        // Una tarjeta ya creada no puede quedar sin título: se restaura el anterior.
        if (nombre === 'titulo' && valor === '' && !li.dataset.nueva) {
            campo.value = campo.dataset.valor ?? '';
            return;
        }
        if (nombre === 'titulo' && valor === '') return;

        const guardado = await guardarCampo({
            tipo: li.dataset.tipo,
            id: li.dataset.id,
            campo: nombre,
            valor,
            indicador: li.querySelector('[data-guardado]'),
        });

        if (guardado) {
            campo.dataset.valor = valor;
            if (nombre === 'titulo') delete li.dataset.nueva;
        }
    }

    async function descartarTarjeta(li) {
        li.remove();
        actualizarPanel();
        try {
            await pedir(urlTarjeta(li.dataset.tipo, li.dataset.id), 'DELETE');
        } catch (error) {
            mostrarAviso(error.message);
        }
    }

    lista.addEventListener('change', async (evento) => {
        const campoFecha = evento.target.closest('[data-fecha-tarjeta]');
        if (campoFecha) {
            if (!campoFecha.value) return;
            const li = campoFecha.closest('[data-tarjeta]');
            campoFecha.disabled = true;
            try {
                await ponerFecha(li.dataset.tipo, li.dataset.id, campoFecha.value);
                li.remove();
                actualizarPanel();
                calendario.refetchEvents();
            } catch (error) {
                campoFecha.value = '';
                campoFecha.disabled = false;
                mostrarAviso(error.message);
            }
            return;
        }

        const campo = evento.target.closest('[data-campo]');
        if (campo) alCambiarCampoDeTarjeta(campo);
    });

    lista.addEventListener('input', (evento) => {
        if (evento.target.matches('textarea')) ajustarAlto(evento.target);
    });

    lista.addEventListener('keydown', (evento) => {
        const campo = evento.target.closest('[data-campo]');
        if (!campo) return;
        const guarda = campo.dataset.campo === 'titulo' ? evento.key === 'Enter' : evento.key === 'Enter' && (evento.ctrlKey || evento.metaKey);
        if (guarda) {
            evento.preventDefault();
            campo.blur();
        }
    });

    // Una tarjeta recién creada que se deja sin título se descarta (también en el servidor).
    lista.addEventListener('focusout', (evento) => {
        const li = evento.target.closest('[data-tarjeta]');
        if (!li || !li.dataset.nueva) return;
        setTimeout(() => {
            if (!li.isConnected || li.contains(document.activeElement)) return;
            const titulo = li.querySelector('[data-campo="titulo"]').value.trim();
            const comentario = li.querySelector('[data-campo="comentario"]').value.trim();
            if (titulo === '' && comentario === '') descartarTarjeta(li);
        }, 0);
    });

    /** "Editar todo" de una tarjeta: despliega adentro de la misma tarjeta contexto o materia, prioridad, estado… */
    async function alternarEdicionCompleta(boton) {
        const li = boton.closest('[data-tarjeta]');
        const extra = li.querySelector('[data-extra]');
        const abrir = extra.hidden;
        const indicador = li.querySelector('[data-guardado]');
        const { tipo, id } = li.dataset;

        boton.setAttribute('aria-expanded', String(abrir));
        li.classList.toggle('es-completa', abrir);
        extra.hidden = !abrir;
        if (!abrir) return;

        const pintar = (detalle) => pintarCamposExtra(extra, detalle, async (cambio) => {
            indicar(indicador, 'Guardando…');
            try {
                const respuesta = await enviar(urlTarjeta(tipo, id), cambio);
                indicar(indicador, 'Guardado');
                if (respuesta.detalle) pintar(respuesta.detalle);
            } catch (error) {
                indicar(indicador, 'No se guardó', true);
                mostrarAviso(error.message);
                pintar(await pedir(urlDe(datos.urlDetalle, tipo, id), 'GET').then((r) => r.detalle));
            }
        }, {
            conPrioridadYColor: true,
            reactivar: async (botonReactivar) => {
                if (await reactivarRecordatorio(id, botonReactivar)) {
                    pintar(await pedir(urlDe(datos.urlDetalle, tipo, id), 'GET').then((r) => r.detalle));
                }
            },
        });

        extra.setAttribute('aria-busy', 'true');
        try {
            pintar((await pedir(urlDe(datos.urlDetalle, tipo, id), 'GET')).detalle);
            extra.querySelector('input, select, button')?.focus();
        } catch (error) {
            extra.hidden = true;
            boton.setAttribute('aria-expanded', 'false');
            li.classList.remove('es-completa');
            mostrarAviso(error.message);
        } finally {
            extra.removeAttribute('aria-busy');
        }
    }

    lista.addEventListener('click', async (evento) => {
        const editar = evento.target.closest('[data-editar-tarjeta]');
        if (editar) {
            // Ctrl/Cmd+clic: el enlace de siempre (formulario completo en otra pestaña).
            if (evento.ctrlKey || evento.metaKey || evento.shiftKey) return;
            evento.preventDefault();
            alternarEdicionCompleta(editar);
            return;
        }

        const borrar = evento.target.closest('[data-borrar]');
        if (!borrar) return;
        const li = borrar.closest('[data-tarjeta]');
        if (!await confirmar(`¿Eliminar ${ARTICULOS[li.dataset.tipo]}? No se puede deshacer.`)) return;
        await descartarTarjeta(li);
    });

    /* Ver más */
    document.querySelectorAll('[data-ver-mas]').forEach((boton) => {
        boton.addEventListener('click', async () => {
            const tipo = boton.dataset.verMas;
            const consulta = new URLSearchParams();
            tarjetasDelPanel().filter((li) => li.dataset.tipo === tipo).forEach((li) => consulta.append('excluir[]', li.dataset.id));
            boton.disabled = true;
            try {
                const respuesta = await pedir(`${urlDe(datos.urlPagina, tipo, '')}?${consulta}`, 'GET');
                const plantilla = document.createElement('template');
                plantilla.innerHTML = respuesta.html;
                [...plantilla.content.children].forEach((nodo) => lista.append(nodo));
                lista.querySelectorAll('textarea').forEach(ajustarAlto);
                boton.dataset.hayMas = respuesta.hayMas ? '1' : '0';
                actualizarPanel();
            } catch (error) {
                mostrarAviso(error.message);
            } finally {
                boton.disabled = false;
            }
        });
    });

    /* Crear tarjeta desde los botones del panel */
    document.querySelectorAll('[data-crear]').forEach((boton) => {
        boton.addEventListener('click', async () => {
            const tipo = boton.dataset.crear;
            boton.disabled = true;
            try {
                const respuesta = await pedir(datos.urlTarjetas, 'POST', { tipo });
                if (filtroPanel !== '' && filtroPanel !== tipo) {
                    document.querySelector('[data-panel-filtro=""]').click();
                }
                const li = insertarTarjeta(respuesta.panel);
                lista.scrollTop = 0;
                li.scrollIntoView({ block: 'nearest' });
                li.querySelector('[data-campo="titulo"]').focus();
            } catch (error) {
                mostrarAviso(error.message);
            } finally {
                boton.disabled = false;
            }
        });
    });

    /* Arrastre desde el panel: se agarra de la cabecera (los campos quedan libres para escribir),
       pero lo que viaja con el puntero es la tarjeta entera. */
    const arrastre = new Draggable(lista, {
        itemSelector: '.tarj',
        longPressDelay: 300,
        eventData: (el) => {
            const li = el.closest('[data-tarjeta]');
            const tipo = li.dataset.tipo;
            const evento = {
                id: `${tipo}-${li.dataset.id}`,
                title: li.querySelector('[data-campo="titulo"]').value.trim() || 'Sin título',
                classNames: [`ev-tipo-${tipo}`],
                extendedProps: { tipo, [`${tipo}Id`]: Number(li.dataset.id) },
                create: true,
            };
            // Tareas y notas viven en el "todo el día"; el recordatorio toma la hora donde se suelta.
            if (tipo !== 'recordatorio') evento.allDay = true;
            return evento;
        },
    });
    arrastre.dragging.pointer.handleSelector = '.tarj-cabeza';

    /* ---------- Popover para crear en un día ---------- */
    const popTipos = document.getElementById('popover-tipos');
    let dia = null; // día tocado: { fecha, hora, rect }

    function posicionar(pop, rect) {
        pop.hidden = false;
        if (esPantallaChica.matches) {
            pop.style.left = pop.style.top = '';
            return;
        }
        const ancho = pop.offsetWidth;
        const alto = pop.offsetHeight;
        const izquierda = Math.max(8, Math.min(rect.left, window.innerWidth - ancho - 8));
        let arriba = rect.bottom + 6;
        if (arriba + alto > window.innerHeight - 8) arriba = Math.max(8, rect.top - alto - 6);
        pop.style.left = `${izquierda}px`;
        pop.style.top = `${arriba}px`;
    }

    const rectPunto = (x, y) => ({ left: x, right: x, top: y, bottom: y });
    const rectDe = (elemento) => {
        const r = elemento.getBoundingClientRect();
        return { left: r.left, right: r.right, top: r.top, bottom: r.bottom };
    };

    function cerrarTipos() {
        popTipos.hidden = true;
        dia = null;
    }

    /* Crear una tarjeta directamente en el día tocado y abrir su detalle */
    popTipos.querySelectorAll('[data-crear-en-dia]').forEach((boton) => {
        boton.addEventListener('click', async () => {
            if (!dia) return;
            const tipo = boton.dataset.crearEnDia;
            const { fecha, hora } = dia;
            cerrarTipos();

            const valor = tipo === 'recordatorio' ? `${fecha}T${hora ?? '09:00:00'}` : fecha;
            try {
                const respuesta = await pedir(datos.urlTarjetas, 'POST', { tipo, fecha: valor });
                calendario.refetchEvents();
                abrirDetalle(tipo, respuesta.tarjeta.id, { nueva: true });
            } catch (error) {
                mostrarAviso(error.message);
            }
        });
    });

    function abrirTipos(info) {
        cerrarDetalle({ devolverFoco: false });
        const conHora = !info.allDay;
        dia = {
            fecha: info.dateStr.slice(0, 10),
            hora: conHora ? horaLocal(info.date) : null,
            rect: info.jsEvent ? rectPunto(info.jsEvent.clientX, info.jsEvent.clientY) : rectDe(info.dayEl),
        };
        document.getElementById('popover-tipos-fecha').textContent =
            `Crear el ${aDiaAMostrar(dia.fecha)}${conHora ? ` a las ${dia.hora.slice(0, 5)}` : ''}`;
        posicionar(popTipos, dia.rect);
        popTipos.querySelector('button').focus();
    }

    // Flechas dentro del menú de tipos
    popTipos.addEventListener('keydown', (e) => {
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
        const botones = [...popTipos.querySelectorAll('button')];
        const i = botones.indexOf(document.activeElement);
        botones[(i + (e.key === 'ArrowDown' ? 1 : -1) + botones.length) % botones.length].focus();
        e.preventDefault();
    });

    window.addEventListener('resize', cerrarTipos);

    /* ---------- Panel lateral de detalle ----------
       Un solo panel: al tocar otra card cambia su contenido sin cerrarse. Los cambios se guardan
       solos (al salir de un campo o al elegir una opción) y se serializan; el calendario se refresca. */
    const det = document.getElementById('detalle');
    const porId = (id) => document.getElementById(`detalle-${id}`);
    const dCuerpo = porId('cuerpo');
    const dPill = porId('tipo');
    const dTitulo = porId('titulo');
    const dTituloFijo = porId('titulo-fijo');
    const dAviso = porId('estado');
    const dComentario = porId('comentario');
    const dComentarioEtiqueta = porId('comentario-etiqueta');
    const dPrioridad = porId('prioridad');
    const dColor = porId('color');
    const dFecha = porId('fecha');
    const dFechaEtiqueta = porId('fecha-etiqueta');
    const dFechaAyuda = porId('fecha-ayuda');
    const dQuitarFecha = porId('quitar-fecha');
    const dFilas = porId('filas');
    const dCompletar = porId('completar');
    const dReactivar = porId('reactivar');
    const dMas = porId('mas');
    const dHistorial = porId('historial');
    const dGuardado = porId('guardado');
    const dBorrar = porId('borrar');
    const dPie = porId('pie');
    const dConfirmar = porId('confirmar');
    const dConfirmarTexto = porId('confirmar-texto');
    const dCampos = [[dTitulo, 'titulo'], [dComentario, 'comentario']];

    let abierto = null; // { tipo, id, nueva, datos }
    let solicitud = 0; // descarta respuestas de detalles que ya no son el abierto
    let cola = Promise.resolve(); // guardados en orden: una respuesta nunca pisa a otra

    const enCola = (tarea) => { cola = cola.then(tarea, tarea); return cola; };
    const idEvento = (tipo, id) => `${tipo}-${id}`;
    const eventosDe = (tipo, id) => [...document.querySelectorAll(`.fc [data-evento="${idEvento(tipo, id)}"]`)];
    const esVisible = (el) => !el.closest('[hidden]');

    function marcarAbierto() {
        document.querySelectorAll('.fc .ev-abierto').forEach((el) => el.classList.remove('ev-abierto'));
        if (abierto) eventosDe(abierto.tipo, abierto.id).forEach((el) => el.classList.add('ev-abierto'));
    }

    function pintarOpciones(contenedor, opciones, actual, alElegir, deshabilitado = false, soloPunto = false) {
        contenedor.replaceChildren();
        opciones.forEach((opcion) => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = soloPunto ? 'detalle-opcion detalle-opcion-color' : 'detalle-opcion';
            boton.setAttribute('role', 'radio');
            boton.setAttribute('aria-checked', String(opcion.valor === actual));
            boton.tabIndex = opcion.valor === actual || (actual == null && contenedor.childElementCount === 0) ? 0 : -1;
            boton.disabled = deshabilitado;
            if (soloPunto) {
                boton.setAttribute('aria-label', opcion.etiqueta);
                boton.title = opcion.etiqueta;
            }
            if (opcion.marca || soloPunto) {
                const punto = document.createElement('i');
                if (opcion.marca) punto.style.setProperty('--punto-opcion', opcion.marca);
                punto.setAttribute('aria-hidden', 'true');
                boton.append(punto);
            }
            if (!soloPunto) boton.append(opcion.etiqueta);
            boton.addEventListener('click', () => alElegir(opcion.valor));
            contenedor.append(boton);
        });
    }

    // Flechas dentro de un grupo de opciones (radiogroup)
    [dPrioridad, dColor].forEach((grupo) => grupo.addEventListener('keydown', (e) => {
        const paso = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
        if (!paso) return;
        const botones = [...grupo.querySelectorAll('button:not(:disabled)')];
        const i = botones.indexOf(document.activeElement);
        if (i < 0) return;
        e.preventDefault();
        const siguiente = botones[(i + paso + botones.length) % botones.length];
        siguiente.focus();
        siguiente.click();
    }));

    /** Lo que cambia con cada guardado: filas de solo lectura, prioridad, color, completada y bloqueos. */
    function pintarResto(d) {
        const bloqueada = Boolean(d.bloqueada);
        dAviso.hidden = !d.vencida;
        dAviso.textContent = d.vencida ? 'Esta tarea está vencida' : '';

        dFilas.replaceChildren();
        (d.filas ?? []).forEach((fila) => {
            const dt = document.createElement('dt');
            const dd = document.createElement('dd');
            dt.textContent = fila.etiqueta;
            dd.textContent = fila.valor;
            dFilas.append(dt, dd);
        });

        if (d.tipo === 'tarea') {
            pintarOpciones(dPrioridad, d.prioridades, d.prioridad, (valor) => guardarCambio({ prioridad: valor }));
            dCompletar.setAttribute('aria-pressed', String(d.completada));
            dCompletar.innerHTML = '';
            const icono = document.createElement('i');
            icono.className = `bi ${d.completada ? 'bi-arrow-counterclockwise' : 'bi-check2-circle'}`;
            icono.setAttribute('aria-hidden', 'true');
            dCompletar.append(icono, d.completada ? ' Reabrir tarea' : ' Marcar como completada');
        }

        dReactivar.hidden = !(d.tipo === 'recordatorio' && d.avisado);

        if (d.tipo === 'nota') {
            pintarOpciones(dColor, [{ valor: null, etiqueta: 'Sin color (usa el del contexto)' }, ...d.colores.map((c) => ({ valor: c.valor, etiqueta: c.etiqueta, marca: c.marca }))], d.color ?? null,
                (valor) => guardarCambio({ color: valor }), false, true);
        }

        if (d.solo_lectura) {
            dMas.replaceChildren();
        } else {
            pintarCamposExtra(dMas, d, (cambio) => guardarCambio(cambio));
        }
        dMas.hidden = Boolean(d.solo_lectura);
        dFilas.hidden = dFilas.childElementCount === 0;

        dFecha.disabled = bloqueada;
        dQuitarFecha.hidden = bloqueada || !d.fecha;
        dFechaAyuda.hidden = !d.ayuda_fecha;
        dFechaAyuda.textContent = d.ayuda_fecha ?? '';
    }

    /** Los campos de texto y la fecha, solo al abrir la card (no pisan lo que se está escribiendo). */
    function pintarCampos(d) {
        const solo = Boolean(d.solo_lectura);
        dTitulo.value = d.titulo ?? '';
        dTitulo.dataset.valor = d.titulo ?? '';
        dTitulo.setAttribute('aria-label', `Título de ${ARTICULOS[d.tipo] ?? ''}`.trim());
        dTituloFijo.textContent = d.titulo ?? '';
        dComentario.value = d.comentario ?? '';
        dComentario.dataset.valor = d.comentario ?? '';
        dComentarioEtiqueta.textContent = d.etiqueta_comentario ?? '';
        dFecha.type = d.tipo === 'recordatorio' ? 'datetime-local' : 'date';
        dFecha.value = solo ? '' : (d.fecha ?? '');
        dFechaEtiqueta.textContent = d.etiqueta_fecha ?? 'Fecha';
        dHistorial.href = d.historial ?? '#';
        dBorrar.setAttribute('aria-label', `Eliminar ${ARTICULOS[d.tipo] ?? ''}`.trim());
    }

    function pintarDetalle(d) {
        abierto.datos = d;
        pintarCampos(d);
        pintarResto(d);
        dCuerpo.setAttribute('aria-busy', 'false');
        dCampos.forEach(([campo]) => ajustarAlto(campo));
    }

    function prepararTipo(tipo) {
        [...det.classList].filter((c) => c.startsWith('tipo-')).forEach((c) => det.classList.remove(c));
        det.classList.add(`tipo-${tipo}`);
        det.querySelectorAll('[data-tipos]').forEach((el) => { el.hidden = !el.dataset.tipos.split(' ').includes(tipo); });
        dPill.textContent = ETIQUETAS[tipo];
    }

    /** Abre el detalle de una card; si ya hay otra abierta, el panel cambia sin cerrarse. */
    async function abrirDetalle(tipo, id, { titulo = '', nueva = false } = {}) {
        cerrarTipos();
        if (abierto && abierto.tipo === tipo && abierto.id === id) {
            if (!det.contains(document.activeElement)) det.focus({ preventScroll: true });
            return;
        }

        const habiaFoco = det.contains(document.activeElement);
        if (abierto) await soltarActual();
        const peticion = ++solicitud;
        abierto = { tipo, id, nueva, datos: null };

        prepararTipo(tipo);
        dTitulo.value = dTituloFijo.textContent = titulo;
        dTitulo.dataset.valor = titulo;
        dComentario.value = '';
        dFilas.replaceChildren();
        dAviso.hidden = true;
        dReactivar.hidden = true;
        dGuardado.textContent = '';
        dPrioridad.replaceChildren();
        dColor.replaceChildren();
        dMas.replaceChildren();
        cancelarConfirmacion();
        dCuerpo.setAttribute('aria-busy', 'true');
        det.hidden = false;
        marcarAbierto();
        if (!habiaFoco || !nueva) det.focus({ preventScroll: true });

        try {
            const respuesta = await pedir(urlDe(datos.urlDetalle, tipo, id), 'GET');
            if (peticion !== solicitud) return;
            pintarDetalle(respuesta.detalle);
            if (nueva) {
                dTitulo.focus();
                dTitulo.select();
            }
        } catch (error) {
            if (peticion !== solicitud) return;
            mostrarAviso(error.message);
            cerrarDetalle({ devolverFoco: false });
        }
    }

    /** Guarda lo pendiente de la card actual y descarta la recién creada que quedó vacía. */
    async function soltarActual() {
        const item = abierto;
        if (!item) return;
        guardarPendiente();

        if (item.nueva && dTitulo.value.trim() === '' && dComentario.value.trim() === '') {
            abierto = null;
            await enCola(async () => {
                try { await pedir(urlTarjeta(item.tipo, item.id), 'DELETE'); } catch (error) { mostrarAviso(error.message); }
            });
            calendario.refetchEvents();
        }
    }

    function cerrarDetalle({ devolverFoco = true } = {}) {
        if (det.hidden) return;
        const item = abierto;
        soltarActual();
        abierto = null;
        solicitud += 1;
        det.hidden = true;
        cancelarConfirmacion();
        marcarAbierto();

        if (devolverFoco && item) {
            const origen = eventosDe(item.tipo, item.id)[0];
            if (origen) origen.focus({ preventScroll: true });
        }
    }

    /* ---------- Guardado ---------- */
    async function guardarCambio(cambio, { campo = null } = {}) {
        const item = abierto;
        if (!item || item.datos?.solo_lectura) return false;
        const previo = campo ? campo.dataset.valor : null;
        if (campo) campo.dataset.valor = campo.value.trim();

        return enCola(async () => {
            indicar(dGuardado, 'Guardando…');
            try {
                const respuesta = await enviar(urlTarjeta(item.tipo, item.id), cambio);
                if (abierto === item && respuesta.detalle) {
                    item.datos = respuesta.detalle;
                    pintarResto(respuesta.detalle);
                }
                indicar(dGuardado, 'Guardado');
                calendario.refetchEvents();
                return true;
            } catch (error) {
                if (campo) campo.dataset.valor = previo ?? '';
                indicar(dGuardado, 'No se guardó', true);
                mostrarAviso(error.message);
                return false;
            }
        });
    }

    function guardarCampoDetalle(campo, nombre) {
        if (!abierto || !abierto.datos || campo.closest('[hidden]')) return;
        const valor = campo.value.trim();
        if (valor === (campo.dataset.valor ?? '')) return;

        // Una card ya creada no puede quedar sin título: se restaura el anterior.
        if (nombre === 'titulo' && valor === '') {
            if (!abierto.nueva) campo.value = campo.dataset.valor ?? '';
            return;
        }

        const item = abierto;
        guardarCambio({ [nombre]: valor }, { campo }).then((guardado) => {
            if (guardado && nombre === 'titulo') item.nueva = false;
        });
    }

    const guardarPendiente = () => dCampos.forEach(([campo, nombre]) => guardarCampoDetalle(campo, nombre));

    dTitulo.addEventListener('change', () => guardarCampoDetalle(dTitulo, 'titulo'));
    dComentario.addEventListener('change', () => guardarCampoDetalle(dComentario, 'comentario'));
    dTitulo.addEventListener('input', () => ajustarAlto(dTitulo));
    dComentario.addEventListener('input', () => ajustarAlto(dComentario));
    dTitulo.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); dTitulo.blur(); }
    });
    dComentario.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); dComentario.blur(); }
    });

    dCompletar.addEventListener('click', async () => {
        const item = abierto;
        if (!item?.datos) return;
        dCompletar.disabled = true;
        await guardarCambio({ completada: !item.datos.completada });
        dCompletar.disabled = false;
    });

    /** Mueve la fecha desde el panel (valor null la quita y devuelve la card a "Por ubicar"). */
    async function cambiarFecha(valor) {
        const item = abierto;
        if (!item?.datos) return;
        const anterior = item.datos.fecha ?? '';
        dFecha.disabled = true;
        dQuitarFecha.disabled = true;

        await enCola(async () => {
            indicar(dGuardado, 'Guardando…');
            try {
                const respuesta = await ponerFecha(item.tipo, item.id, valor);
                indicar(dGuardado, 'Guardado');
                item.datos.fecha = valor;
                if (!valor) {
                    if (respuesta.panel) insertarTarjeta(respuesta.panel);
                    if (abierto === item) cerrarDetalle({ devolverFoco: false });
                } else if (abierto === item) {
                    pintarResto(item.datos);
                }
                calendario.refetchEvents();
            } catch (error) {
                dFecha.value = anterior;
                indicar(dGuardado, 'No se guardó', true);
                mostrarAviso(error.message);
            }
        });

        if (abierto === item) {
            dFecha.disabled = Boolean(item.datos.bloqueada);
            dQuitarFecha.disabled = false;
        }
    }

    dFecha.addEventListener('change', () => {
        // Un valor vacío es una edición a medias del campo: la fecha solo se quita con el botón.
        if (!dFecha.value) { dFecha.value = abierto?.datos?.fecha ?? ''; return; }
        cambiarFecha(dFecha.value);
    });
    dQuitarFecha.addEventListener('click', () => cambiarFecha(null));

    /* Marcar como no avisado: el detalle se vuelve a pedir (estado, fecha editable y ayuda salen del servidor). */
    dReactivar.addEventListener('click', async () => {
        const item = abierto;
        if (!item || item.tipo !== 'recordatorio' || !(await reactivarRecordatorio(item.id, dReactivar))) return;
        try {
            const { detalle } = await pedir(urlDe(datos.urlDetalle, item.tipo, item.id), 'GET');
            if (abierto === item) {
                item.datos = detalle;
                pintarResto(detalle);
            }
        } catch (error) {
            mostrarAviso(error.message);
        }
    });

    /** Si la card abierta se movió arrastrándola en el calendario, el panel refleja la nueva fecha. */
    function sincronizarFecha(tipo, id, valor) {
        if (abierto?.datos && abierto.tipo === tipo && abierto.id === id) {
            abierto.datos.fecha = valor;
            dFecha.value = valor ?? '';
            pintarResto(abierto.datos);
        }
    }

    /* ---------- Eliminar con confirmación dentro del panel ---------- */
    function cancelarConfirmacion() {
        dConfirmar.hidden = true;
        dPie.hidden = false;
    }

    dBorrar.addEventListener('click', () => {
        if (!abierto?.datos) return;
        const nombre = (dTitulo.value || dTituloFijo.textContent || abierto?.datos?.titulo || '').trim();
        dConfirmarTexto.textContent = `¿Eliminar ${ARTICULOS[abierto.tipo]}${nombre ? ` «${nombre}»` : ''}? No se puede deshacer.`;
        dPie.hidden = true;
        dConfirmar.hidden = false;
        porId('confirmar-no').focus();
    });

    porId('confirmar-no').addEventListener('click', () => {
        cancelarConfirmacion();
        dBorrar.focus();
    });

    porId('confirmar-si').addEventListener('click', async () => {
        const item = abierto;
        if (!item) return;
        const boton = porId('confirmar-si');
        boton.disabled = true;
        try {
            await enCola(() => pedir(urlTarjeta(item.tipo, item.id), 'DELETE'));
            item.nueva = false;
            if (abierto === item) cerrarDetalle({ devolverFoco: false });
            calendario.refetchEvents();
        } catch (error) {
            mostrarAviso(error.message);
        } finally {
            boton.disabled = false;
        }
    });

    det.querySelector('[data-detalle-cerrar]').addEventListener('click', () => cerrarDetalle());

    /* ---------- Teclado y clic fuera ---------- */
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (!popTipos.hidden) { cerrarTipos(); return; }
        if (det.hidden) return;
        if (!dConfirmar.hidden) { cancelarConfirmacion(); dBorrar.focus(); return; }
        cerrarDetalle();
    });

    // Foco atrapado dentro del panel mientras está abierto (Esc lo cierra y devuelve el foco a la card).
    det.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab') return;
        const enfocables = [...det.querySelectorAll('a[href], button, input, textarea, select, [tabindex="0"]')]
            .filter((el) => !el.disabled && el.tabIndex >= 0 && esVisible(el));
        if (enfocables.length === 0) return;
        const primero = enfocables[0];
        const ultimo = enfocables[enfocables.length - 1];
        if (e.shiftKey && (document.activeElement === primero || document.activeElement === det)) {
            e.preventDefault();
            ultimo.focus();
        } else if (!e.shiftKey && document.activeElement === ultimo) {
            e.preventDefault();
            primero.focus();
        }
    });

    // Clic fuera lo cierra, salvo sobre otra card del calendario (que solo cambia el contenido).
    document.addEventListener('pointerdown', (e) => {
        if (!popTipos.hidden && !popTipos.contains(e.target)) cerrarTipos();
        if (!det.hidden && !det.contains(e.target) && !e.target.closest('.fc-event, .fc-popover')) {
            cerrarDetalle({ devolverFoco: false });
        }
    });

    /* ---------- Calendario ---------- */
    function dentroDelPanel(jsEvent) {
        const punto = jsEvent.changedTouches?.[0] ?? jsEvent;
        const caja = panel.getBoundingClientRect();
        return punto.clientX >= caja.left && punto.clientX <= caja.right
            && punto.clientY >= caja.top && punto.clientY <= caja.bottom;
    }

    const idDe = (props) => props[`${props.tipo}Id`];

    /** Mover o estirar una caja del planner: se guarda en la caja (planner y hoja del día lo reflejan) o se deshace. */
    async function guardarMovimientoPlanner(info) {
        const evento = info.event;
        if (!evento.start) {
            info.revert();
            return;
        }

        try {
            await moverPlanner(evento.extendedProps.plannerId, datosPlanner(evento, 'endDelta' in info));
            calendario.refetchEvents();
        } catch (error) {
            info.revert();
            mostrarAviso(error.message);
        }
    }

    const calendario = new Calendar(elCalendario, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        locale: esLocale,
        firstDay: 1,
        initialView: esCelular.matches ? 'listWeek' : 'dayGridMonth',
        initialDate: datos.fecha,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día', list: 'Lista' },
        height: 'auto',
        nowIndicator: true,
        dayMaxEvents: 3,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        slotMinTime: '06:00:00',
        slotMaxTime: '24:00:00',
        scrollTime: '08:00:00',
        // Un evento puntual (recordatorio, sesión sin fin) ocupa una hora y nunca una píldora ilegible.
        defaultTimedEventDuration: '01:00:00',
        forceEventDuration: true,
        slotDuration: '00:30:00',
        eventMinHeight: 60,
        eventShortHeight: 40,
        slotEventOverlap: false,
        allDayText: 'Todo el día',
        noEventsText: 'No hay nada en este período.',
        longPressDelay: 300,
        eventLongPressDelay: 300,
        editable: true,
        eventDurationEditable: false, // solo las cajas del planner se estiran (durationEditable por evento)
        droppable: true,

        events: async (info, exito, fallo) => {
            const tipos = tiposActivos();
            if (tipos.length === 0) {
                exito([]);
                return;
            }

            try {
                const consulta = new URLSearchParams({ start: info.startStr, end: info.endStr, tipos: tipos.join(',') });
                const respuesta = await pedirSeguro(`${datos.urlEventos}?${consulta}`);
                if (!respuesta.ok) throw new Error('No se pudieron cargar los eventos del calendario.');
                exito(await respuesta.json());
            } catch (error) {
                mostrarAviso(error.message);
                fallo(error);
            }
        },

        dateClick: (info) => abrirTipos(info),

        eventClick: (info) => {
            const props = info.event.extendedProps;
            info.jsEvent.preventDefault();
            // Las cajas del planner se ven y se editan en la hoja del día de la agenda.
            if (props.tipo === 'planner') {
                window.location.assign(props.urlDia);
                return;
            }
            abrirDetalle(props.tipo, idDe(props), { titulo: info.event.title });
        },

        // Tareas y notas solo viven en el "todo el día"; los recordatorios y las cajas del planner aceptan cualquier hueco.
        eventAllow: (destino, evento) => (['recordatorio', 'planner'].includes(evento.extendedProps.tipo) ? true : destino.allDay),

        eventDidMount: (info) => {
            const props = info.event.extendedProps;
            const partes = [info.event.title];
            if (props.tipo === 'tarea') {
                partes.push(`prioridad ${props.prioridadEtiqueta?.toLowerCase() ?? 'media'}`);
                if (props.completada) partes.push('completada');
                if (props.vencida) partes.push('vencida');
            } else if (props.tipo === 'planner') {
                partes.push(props.actividad ? `planner semanal, ${props.actividad}` : 'planner semanal');
            } else if (props.tipo === 'recordatorio' && props.avisado) {
                partes.push('ya avisado');
            } else if (props.tipo === 'sesion') {
                partes.push(`sesión de estudio, ${props.estado?.toLowerCase()}`);
            }
            info.el.title = partes.join(' · ');
            info.el.dataset.evento = info.event.id;
            if (abierto && info.event.id === idEvento(abierto.tipo, abierto.id)) info.el.classList.add('ev-abierto');
        },

        // Tarjeta soltada desde el panel: se guarda la fecha o se deshace.
        eventReceive: async (info) => {
            const evento = info.event;
            const { tipo } = evento.extendedProps;
            const id = idDe(evento.extendedProps);
            const valor = tipo === 'recordatorio' ? momentoRecordatorio(evento.start, evento.allDay) : fechaLocal(evento.start);
            evento.remove();

            try {
                await ponerFecha(tipo, id, valor);
                quitarDelPanel(tipo, id);
            } catch (error) {
                mostrarAviso(error.message);
            }
            calendario.refetchEvents();
        },

        // Estirar una caja del planner cambia su hora de fin (info trae endDelta: ahí sí se envía el fin).
        eventResize: (info) => guardarMovimientoPlanner(info),

        eventDragStart: (info) => {
            if (TIPOS_TARJETA.includes(info.event.extendedProps.tipo)) panel.classList.add('panel-destino');
        },

        // Arrastrar un evento de vuelta al panel le quita la fecha y vuelve a ser tarjeta.
        eventDragStop: async (info) => {
            panel.classList.remove('panel-destino');
            const evento = info.event;
            const { tipo } = evento.extendedProps;
            if (!TIPOS_TARJETA.includes(tipo) || !dentroDelPanel(info.jsEvent)) return;

            try {
                const respuesta = await ponerFecha(tipo, idDe(evento.extendedProps), null);
                evento.remove();
                if (respuesta.panel) insertarTarjeta(respuesta.panel);
                if (abierto?.tipo === tipo && abierto.id === idDe(evento.extendedProps)) cerrarDetalle({ devolverFoco: false });
                calendario.refetchEvents();
            } catch (error) {
                mostrarAviso(error.message);
            }
        },

        eventDrop: async (info) => {
            const evento = info.event;
            const { tipo } = evento.extendedProps;
            if (tipo === 'planner') {
                await guardarMovimientoPlanner(info);
                return;
            }
            if (!evento.start || !TIPOS_TARJETA.includes(tipo) || (tipo !== 'recordatorio' && !evento.allDay)) {
                info.revert();
                return;
            }

            try {
                const valor = tipo === 'recordatorio' ? momentoRecordatorio(evento.start, evento.allDay) : fechaLocal(evento.start);
                await ponerFecha(tipo, idDe(evento.extendedProps), valor);
                sincronizarFecha(tipo, idDe(evento.extendedProps), tipo === 'recordatorio' ? valor.slice(0, 16) : valor);
                calendario.refetchEvents();
            } catch (error) {
                info.revert();
                mostrarAviso(error.message);
            }
        },
    });

    calendario.render();
    actualizarPanel();

    checks.forEach((c) => c.addEventListener('change', () => {
        guardarFiltros(tiposActivos());
        calendario.refetchEvents();
    }));

    // En pantallas angostas la grilla mensual no entra: pasa a lista.
    esCelular.addEventListener('change', (e) => {
        if (e.matches && calendario.view.type === 'dayGridMonth') calendario.changeView('listWeek');
    });
}
