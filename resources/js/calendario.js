import { Calendar } from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin, { Draggable } from '@fullcalendar/interaction';
import '../css/calendario.css';

const CLAVE_FILTROS = 'focodiario.calendario.tipos';
const TIPOS = ['tarea', 'recordatorio', 'nota', 'sesion'];
const TIPOS_TARJETA = ['tarea', 'recordatorio', 'nota'];
const ICONOS = {
    tarea: 'bi-check2-square',
    recordatorio: 'bi-bell',
    nota: 'bi-journal-text',
    sesion: 'bi-mortarboard',
};
const ETIQUETAS = { tarea: 'Tarea', recordatorio: 'Recordatorio', nota: 'Nota' };
const ARTICULOS = { tarea: 'la tarea', recordatorio: 'el recordatorio', nota: 'la nota' };
const ETIQUETAS_FECHA = { tarea: 'Fecha límite', recordatorio: 'Fecha y hora del aviso', nota: 'Fecha' };
const AYUDA_BLOQUEADA = {
    tarea: 'Las tareas completadas no se pueden reubicar.',
    recordatorio: 'Los recordatorios ya avisados no se pueden reubicar.',
};

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
    const aviso = document.getElementById('calendario-aviso');
    const checks = [...document.querySelectorAll('[data-filtro-tipo]')];
    const esCelular = window.matchMedia('(max-width: 767.98px)');
    const esPantallaChica = window.matchMedia('(max-width: 575.98px)');
    let temporizadorAviso;
    let filtroPanel = '';

    /* ---------- Utilidades ---------- */
    const dosDigitos = (n) => String(n).padStart(2, '0');
    const fechaLocal = (d) => `${d.getFullYear()}-${dosDigitos(d.getMonth() + 1)}-${dosDigitos(d.getDate())}`;
    const horaLocal = (d) => `${dosDigitos(d.getHours())}:${dosDigitos(d.getMinutes())}:${dosDigitos(d.getSeconds())}`;
    const fechaHoraLocal = (d) => `${fechaLocal(d)}T${horaLocal(d)}`;
    const aDiaAMostrar = (iso) => iso.slice(0, 10).split('-').reverse().join('/');

    function mostrarAviso(mensaje) {
        aviso.textContent = mensaje;
        aviso.hidden = false;
        clearTimeout(temporizadorAviso);
        temporizadorAviso = setTimeout(() => { aviso.hidden = true; }, 7000);
    }

    function ajustarAlto(campo) {
        campo.style.height = 'auto';
        campo.style.height = `${campo.scrollHeight}px`;
    }

    /* ---------- Filtros por tipo del calendario (se recuerdan en localStorage) ---------- */
    function leerFiltros() {
        try {
            const guardado = JSON.parse(localStorage.getItem(CLAVE_FILTROS));
            if (Array.isArray(guardado)) {
                return guardado.filter((tipo) => TIPOS.includes(tipo));
            }
        } catch (e) { /* sin almacenamiento: se muestran todos */ }
        return [...TIPOS];
    }

    function guardarFiltros(tipos) {
        try { localStorage.setItem(CLAVE_FILTROS, JSON.stringify(tipos)); } catch (e) { /* ignorar */ }
    }

    function tiposActivos() {
        return checks.filter((c) => c.checked).map((c) => c.value);
    }

    const inicial = leerFiltros();
    checks.forEach((c) => { c.checked = inicial.includes(c.value); });

    /* ---------- Peticiones ---------- */
    const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    async function pedir(url, metodo, cuerpo) {
        const opciones = {
            method: metodo,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token() },
        };
        if (cuerpo !== undefined) {
            opciones.headers['Content-Type'] = 'application/json';
            opciones.body = JSON.stringify(cuerpo);
        }

        let respuesta;
        try {
            respuesta = await fetch(url, opciones);
        } catch (e) {
            throw new Error('No hay conexión con el servidor. Probá de nuevo.');
        }

        let json = null;
        try { json = await respuesta.json(); } catch (e) { /* respuesta sin contenido */ }

        if (!respuesta.ok) {
            const primerError = json?.errors ? Object.values(json.errors)[0]?.[0] : null;
            throw new Error(primerError || json?.message || 'No se pudo guardar el cambio. Probá de nuevo.');
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

    /** Asigna (valor) o quita (null) la fecha. Recordatorio: fecha y hora; tarea y nota: solo el día. */
    function ponerFecha(tipo, id, valor) {
        if (tipo === 'recordatorio') {
            const conSegundos = valor && valor.length === 16 ? `${valor}:00` : valor;
            return enviar(urlFecha(tipo, id), { recordar_en: conSegundos });
        }
        return enviar(urlFecha(tipo, id), { fecha: valor ? valor.slice(0, 10) : null });
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

    lista.addEventListener('click', async (evento) => {
        const borrar = evento.target.closest('[data-borrar]');
        if (!borrar) return;
        const li = borrar.closest('[data-tarjeta]');
        if (!window.confirm(`¿Eliminar ${ARTICULOS[li.dataset.tipo]}? No se puede deshacer.`)) return;
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

    /* Arrastre desde el panel: solo la cabecera arrastra, los campos quedan libres para escribir. */
    new Draggable(lista, {
        itemSelector: '.tarj-cabeza',
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

    /* ---------- Popovers (menú de tipo y editor simple) ---------- */
    const popTipos = document.getElementById('popover-tipos');
    const popEditor = document.getElementById('popover-editor');
    const edTitulo = document.getElementById('editor-titulo');
    const edComentario = document.getElementById('editor-comentario');
    const edFecha = document.getElementById('editor-fecha');
    const edFechaEtiqueta = document.getElementById('editor-fecha-etiqueta');
    const edAyuda = document.getElementById('editor-fecha-ayuda');
    const edGuardado = document.getElementById('editor-guardado');
    const edTipo = document.getElementById('editor-tipo');
    let dia = null; // día tocado: { fecha, hora, rect }
    let editor = null; // tarjeta abierta en el editor
    let retorno = null; // elemento que recupera el foco al cerrar

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

    const rectDe = (elemento) => {
        const r = elemento.getBoundingClientRect();
        return { left: r.left, right: r.right, top: r.top, bottom: r.bottom };
    };
    const rectPunto = (x, y) => ({ left: x, right: x, top: y, bottom: y });

    function cerrarTipos() {
        popTipos.hidden = true;
        dia = null;
    }

    async function cerrarEditor({ devolverFoco = true } = {}) {
        if (popEditor.hidden || !editor) return;
        const actual = editor;
        editor = null;
        popEditor.hidden = true;

        // Recién creada y sin título ni comentario: se descarta.
        if (actual.nueva && edTitulo.value.trim() === '' && edComentario.value.trim() === '') {
            try {
                await pedir(urlTarjeta(actual.tipo, actual.id), 'DELETE');
            } catch (error) {
                mostrarAviso(error.message);
            }
            calendario.refetchEvents();
        }

        if (devolverFoco && retorno?.isConnected) retorno.focus();
        retorno = null;
    }

    function abrirEditor(tarjeta, rect, { nueva = false, origen = null } = {}) {
        cerrarTipos();
        if (!popEditor.hidden) cerrarEditor({ devolverFoco: false });

        editor = { tipo: tarjeta.tipo, id: tarjeta.id, nueva, datos: tarjeta };
        retorno = origen;

        popEditor.className = `popover-foco popover-editor tipo-${tarjeta.tipo}`;
        edTipo.innerHTML = '';
        const icono = document.createElement('i');
        icono.className = `bi ${ICONOS[tarjeta.tipo]}`;
        icono.setAttribute('aria-hidden', 'true');
        edTipo.append(icono, ` ${ETIQUETAS[tarjeta.tipo]}`);

        edTitulo.value = tarjeta.titulo ?? '';
        edTitulo.dataset.valor = tarjeta.titulo ?? '';
        edTitulo.setAttribute('aria-label', `Título de ${ARTICULOS[tarjeta.tipo]}`);
        edComentario.value = tarjeta.comentario ?? '';
        edComentario.dataset.valor = tarjeta.comentario ?? '';

        edFecha.type = tarjeta.tipo === 'recordatorio' ? 'datetime-local' : 'date';
        edFecha.value = tarjeta.fecha ?? '';
        edFecha.disabled = Boolean(tarjeta.bloqueada);
        edFechaEtiqueta.textContent = ETIQUETAS_FECHA[tarjeta.tipo];
        edAyuda.hidden = !tarjeta.bloqueada;
        edAyuda.textContent = AYUDA_BLOQUEADA[tarjeta.tipo] ?? '';

        document.getElementById('editor-mas').href = tarjeta.editar;
        document.getElementById('editor-mas').setAttribute('aria-label', `Más opciones de ${ARTICULOS[tarjeta.tipo]}`);
        document.getElementById('editor-borrar').setAttribute('aria-label', `Eliminar ${ARTICULOS[tarjeta.tipo]}`);
        edGuardado.textContent = '';

        posicionar(popEditor, rect);
        ajustarAlto(edComentario);
        edTitulo.focus();
        if (nueva) edTitulo.select();
    }

    async function guardarCampoDelEditor(campo, nombre) {
        if (!editor) return;
        const valor = campo.value.trim();
        if (valor === (campo.dataset.valor ?? '')) return;

        if (nombre === 'titulo' && valor === '') {
            if (!editor.nueva) campo.value = campo.dataset.valor ?? '';
            return;
        }

        const guardado = await guardarCampo({ ...editor, campo: nombre, valor, indicador: edGuardado });
        if (guardado) {
            campo.dataset.valor = valor;
            if (nombre === 'titulo' && editor) editor.nueva = false;
            calendario.refetchEvents();
        }
    }

    edTitulo.addEventListener('change', () => guardarCampoDelEditor(edTitulo, 'titulo'));
    edComentario.addEventListener('change', () => guardarCampoDelEditor(edComentario, 'comentario'));
    edComentario.addEventListener('input', () => ajustarAlto(edComentario));
    edTitulo.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); edTitulo.blur(); }
    });
    edComentario.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); edComentario.blur(); }
    });

    edFecha.addEventListener('change', async () => {
        if (!editor) return;
        const { tipo, id } = editor;
        edFecha.disabled = true;
        indicar(edGuardado, 'Guardando…');
        try {
            const respuesta = await ponerFecha(tipo, id, edFecha.value || null);
            indicar(edGuardado, 'Guardado');
            if (editor) editor.datos.fecha = edFecha.value || null;
            if (!edFecha.value) {
                // Sin fecha vuelve a ser tarjeta del panel.
                if (respuesta.panel) insertarTarjeta(respuesta.panel);
                editor = null;
                popEditor.hidden = true;
                retorno = null;
            }
            calendario.refetchEvents();
        } catch (error) {
            indicar(edGuardado, 'No se guardó', true);
            edFecha.value = editor?.datos.fecha ?? '';
            mostrarAviso(error.message);
        } finally {
            edFecha.disabled = Boolean(editor?.datos.bloqueada);
        }
    });

    document.getElementById('editor-borrar').addEventListener('click', async () => {
        if (!editor) return;
        const { tipo, id } = editor;
        if (!window.confirm(`¿Eliminar ${ARTICULOS[tipo]}? No se puede deshacer.`)) return;
        try {
            await pedir(urlTarjeta(tipo, id), 'DELETE');
            editor = null;
            popEditor.hidden = true;
            retorno = null;
            calendario.refetchEvents();
        } catch (error) {
            mostrarAviso(error.message);
        }
    });

    popEditor.querySelector('[data-editor-cerrar]').addEventListener('click', () => cerrarEditor());

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (!popEditor.hidden) cerrarEditor();
        if (!popTipos.hidden) cerrarTipos();
    });

    document.addEventListener('pointerdown', (e) => {
        if (!popEditor.hidden && !popEditor.contains(e.target)) cerrarEditor({ devolverFoco: false });
        if (!popTipos.hidden && !popTipos.contains(e.target)) cerrarTipos();
    });

    window.addEventListener('resize', () => {
        cerrarTipos();
        if (!popEditor.hidden) cerrarEditor({ devolverFoco: false });
    });

    /* Crear una tarjeta directamente en el día tocado */
    popTipos.querySelectorAll('[data-crear-en-dia]').forEach((boton) => {
        boton.addEventListener('click', async () => {
            if (!dia) return;
            const tipo = boton.dataset.crearEnDia;
            const { fecha, hora, rect } = dia;
            cerrarTipos();

            const valor = tipo === 'recordatorio' ? `${fecha}T${hora ?? '09:00:00'}` : fecha;
            try {
                const respuesta = await pedir(datos.urlTarjetas, 'POST', { tipo, fecha: valor });
                await new Promise((resolver) => { calendario.refetchEvents(); setTimeout(resolver, 0); });
                abrirEditor(respuesta.tarjeta, rect, { nueva: true });
            } catch (error) {
                mostrarAviso(error.message);
            }
        });
    });

    function abrirTipos(info) {
        if (!popEditor.hidden) cerrarEditor({ devolverFoco: false });
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

    /* ---------- Calendario ---------- */
    function dentroDelPanel(jsEvent) {
        const punto = jsEvent.changedTouches?.[0] ?? jsEvent;
        const caja = panel.getBoundingClientRect();
        return punto.clientX >= caja.left && punto.clientX <= caja.right
            && punto.clientY >= caja.top && punto.clientY <= caja.bottom;
    }

    const idDe = (props) => props[`${props.tipo}Id`];

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
        allDayText: 'Todo el día',
        noEventsText: 'No hay nada en este período.',
        longPressDelay: 300,
        eventLongPressDelay: 300,
        editable: true,
        eventDurationEditable: false,
        droppable: true,

        events: async (info, exito, fallo) => {
            const tipos = tiposActivos();
            if (tipos.length === 0) {
                exito([]);
                return;
            }

            try {
                const consulta = new URLSearchParams({ start: info.startStr, end: info.endStr, tipos: tipos.join(',') });
                const respuesta = await fetch(`${datos.urlEventos}?${consulta}`, { headers: { Accept: 'application/json' } });
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
            if (!TIPOS_TARJETA.includes(props.tipo)) return; // las sesiones de estudio abren su historial
            info.jsEvent.preventDefault();
            abrirEditor(props.tarjeta, rectDe(info.el), { origen: info.el });
        },

        // Tareas y notas solo viven en el "todo el día"; los recordatorios aceptan cualquier hueco.
        eventAllow: (destino, evento) => (evento.extendedProps.tipo === 'recordatorio' ? true : destino.allDay),

        eventDidMount: (info) => {
            const props = info.event.extendedProps;
            const partes = [info.event.title];
            if (props.tipo === 'tarea') {
                partes.push(`prioridad ${props.prioridadEtiqueta?.toLowerCase() ?? 'media'}`);
                if (props.completada) partes.push('completada');
                if (props.vencida) partes.push('vencida');
            } else if (props.tipo === 'recordatorio' && props.avisado) {
                partes.push('ya avisado');
            } else if (props.tipo === 'sesion') {
                partes.push(`sesión de estudio, ${props.estado?.toLowerCase()}`);
            }
            info.el.title = partes.join(' · ');

            const titulo = info.el.querySelector('.fc-event-title, .fc-list-event-title a, .fc-list-event-title');
            if (titulo && ICONOS[props.tipo]) {
                const icono = document.createElement('i');
                icono.className = `bi ${ICONOS[props.tipo]} ev-icono`;
                icono.setAttribute('aria-hidden', 'true');
                titulo.prepend(icono);
            }
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
                calendario.refetchEvents();
            } catch (error) {
                mostrarAviso(error.message);
            }
        },

        eventDrop: async (info) => {
            const evento = info.event;
            const { tipo } = evento.extendedProps;
            if (!evento.start || !TIPOS_TARJETA.includes(tipo) || (tipo !== 'recordatorio' && !evento.allDay)) {
                info.revert();
                return;
            }

            try {
                const valor = tipo === 'recordatorio' ? momentoRecordatorio(evento.start, evento.allDay) : fechaLocal(evento.start);
                await ponerFecha(tipo, idDe(evento.extendedProps), valor);
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
