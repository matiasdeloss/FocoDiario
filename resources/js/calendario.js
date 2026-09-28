import { Calendar } from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin, { Draggable } from '@fullcalendar/interaction';
import { Modal } from 'bootstrap';
import '../css/calendario.css';

const CLAVE_FILTROS = 'focodiario.calendario.tipos';
const TIPOS = ['tarea', 'recordatorio', 'nota', 'sesion'];
const ICONOS = {
    tarea: 'bi-check2-square',
    recordatorio: 'bi-bell',
    nota: 'bi-journal-text',
    sesion: 'bi-mortarboard',
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
    const aviso = document.getElementById('calendario-aviso');
    const checks = [...document.querySelectorAll('[data-filtro-tipo]')];
    const esCelular = window.matchMedia('(max-width: 767.98px)');
    let temporizadorAviso;

    /* ---------- Avisos ---------- */
    function mostrarAviso(mensaje) {
        aviso.textContent = mensaje;
        aviso.hidden = false;
        clearTimeout(temporizadorAviso);
        temporizadorAviso = setTimeout(() => { aviso.hidden = true; }, 7000);
    }

    /* ---------- Filtros por tipo (se recuerdan en localStorage) ---------- */
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
    async function enviar(url, cuerpo) {
        const respuesta = await fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(cuerpo),
        });

        let json = null;
        try { json = await respuesta.json(); } catch (e) { /* respuesta sin JSON */ }

        if (!respuesta.ok) {
            const primerError = json?.errors ? Object.values(json.errors)[0]?.[0] : null;
            throw new Error(primerError || json?.message || 'No se pudo guardar el cambio. Probá de nuevo.');
        }

        return json;
    }

    const urlTarea = (id) => datos.urlTarea.replace('__ID__', id);
    const urlRecordatorio = (id) => datos.urlRecordatorio.replace('__ID__', id);

    /* ---------- Panel "Tareas sin fecha" ---------- */
    function actualizarVacio() {
        document.getElementById('sin-fecha-vacio').hidden = lista.children.length > 0;
    }

    function quitarDelPanel(id) {
        lista.querySelector(`[data-tarea-id="${id}"]`)?.remove();
        actualizarVacio();
    }

    function devolverAlPanel(html) {
        lista.insertAdjacentHTML('afterbegin', html);
        actualizarVacio();
    }

    new Draggable(lista, {
        // Solo la cabecera arrastra: el campo de fecha queda libre para usarse con teclado.
        itemSelector: '.tarea-arrastrable-cabeza',
        longPressDelay: 300,
        eventData: (el) => {
            const item = el.closest('.tarea-arrastrable');
            return {
                id: `tarea-${item.dataset.tareaId}`,
                title: item.dataset.titulo,
                allDay: true,
                classNames: ['ev-tipo-tarea', `ev-prio-${item.dataset.prioridad}`],
                extendedProps: { tipo: 'tarea', tareaId: Number(item.dataset.tareaId) },
                create: true,
            };
        },
    });

    lista.addEventListener('change', async (evento) => {
        const campo = evento.target.closest('[data-fecha-tarea]');
        if (!campo || !campo.value) return;

        const id = campo.closest('.tarea-arrastrable').dataset.tareaId;
        campo.disabled = true;
        try {
            await enviar(urlTarea(id), { fecha: campo.value });
            quitarDelPanel(id);
            calendario.refetchEvents();
        } catch (error) {
            campo.value = '';
            campo.disabled = false;
            mostrarAviso(error.message);
        }
    });

    /* ---------- Modal "agregar al día" ---------- */
    const modalEl = document.getElementById('modal-dia');
    const modal = Modal.getOrCreateInstance(modalEl);
    const campoDia = document.getElementById('modal-dia-fecha');

    function actualizarEnlaces() {
        const fecha = campoDia.value;
        const base = { tarea: datos.urlTareaNueva, recordatorio: datos.urlRecordatorioNuevo, nota: datos.urlNotaNueva };
        modalEl.querySelectorAll('[data-nuevo]').forEach((a) => {
            a.href = `${base[a.dataset.nuevo]}?fecha=${encodeURIComponent(fecha)}`;
            a.classList.toggle('disabled', !fecha);
        });
    }

    function abrirModal(fecha) {
        campoDia.value = fecha;
        actualizarEnlaces();
        modal.show();
    }

    campoDia.addEventListener('input', actualizarEnlaces);
    document.querySelectorAll('[data-abrir-modal]').forEach((boton) => {
        boton.addEventListener('click', () => abrirModal(campoDia.value || datos.fecha));
    });

    /* ---------- Calendario ---------- */
    const dosDigitos = (n) => String(n).padStart(2, '0');
    const fechaLocal = (d) => `${d.getFullYear()}-${dosDigitos(d.getMonth() + 1)}-${dosDigitos(d.getDate())}`;
    const fechaHoraLocal = (d) => `${fechaLocal(d)}T${dosDigitos(d.getHours())}:${dosDigitos(d.getMinutes())}:${dosDigitos(d.getSeconds())}`;

    function dentroDelPanel(jsEvent) {
        const punto = jsEvent.changedTouches?.[0] ?? jsEvent;
        const caja = panel.getBoundingClientRect();
        return punto.clientX >= caja.left && punto.clientX <= caja.right
            && punto.clientY >= caja.top && punto.clientY <= caja.bottom;
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
            right: 'dayGridMonth,timeGridWeek,listWeek',
        },
        buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', list: 'Lista' },
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

        dateClick: (info) => abrirModal(info.dateStr.slice(0, 10)),

        // Las tareas solo viven en el "todo el día"; los recordatorios necesitan hora.
        eventAllow: (destino, evento) => {
            const tipo = evento.extendedProps.tipo;
            if (tipo === 'tarea') return destino.allDay;
            return true;
        },

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

            const titulo = info.el.querySelector('.fc-event-title, .fc-list-event-title a');
            if (titulo && ICONOS[props.tipo]) {
                const icono = document.createElement('i');
                icono.className = `bi ${ICONOS[props.tipo]} ev-icono`;
                icono.setAttribute('aria-hidden', 'true');
                titulo.prepend(icono);
            }
        },

        // Tarea nueva desde el panel: se guarda la fecha o se deshace.
        eventReceive: async (info) => {
            const evento = info.event;
            const id = evento.extendedProps.tareaId;
            const fecha = fechaLocal(evento.start);
            evento.remove();

            try {
                await enviar(urlTarea(id), { fecha });
                quitarDelPanel(id);
            } catch (error) {
                mostrarAviso(error.message);
            }
            calendario.refetchEvents();
        },

        eventDragStart: (info) => {
            if (info.event.extendedProps.tipo === 'tarea') panel.classList.add('panel-destino');
        },

        // Arrastrar una tarea de vuelta al panel le quita la fecha.
        eventDragStop: async (info) => {
            panel.classList.remove('panel-destino');
            const evento = info.event;
            if (evento.extendedProps.tipo !== 'tarea' || !dentroDelPanel(info.jsEvent)) return;

            try {
                const respuesta = await enviar(urlTarea(evento.extendedProps.tareaId), { fecha: null });
                evento.remove();
                if (respuesta.panel) devolverAlPanel(respuesta.panel);
                calendario.refetchEvents();
            } catch (error) {
                mostrarAviso(error.message);
            }
        },

        eventDrop: async (info) => {
            const evento = info.event;
            const props = evento.extendedProps;
            if (!evento.start || evento.allDay !== (props.tipo === 'tarea') || (props.tipo !== 'tarea' && props.tipo !== 'recordatorio')) {
                info.revert();
                return;
            }

            try {
                if (props.tipo === 'tarea') {
                    await enviar(urlTarea(props.tareaId), { fecha: fechaLocal(evento.start) });
                } else {
                    await enviar(urlRecordatorio(props.recordatorioId), { recordar_en: fechaHoraLocal(evento.start) });
                }
                calendario.refetchEvents();
            } catch (error) {
                info.revert();
                mostrarAviso(error.message);
            }
        },
    });

    calendario.render();

    checks.forEach((c) => c.addEventListener('change', () => {
        guardarFiltros(tiposActivos());
        calendario.refetchEvents();
    }));

    // En pantallas angostas la grilla mensual no entra: pasa a lista.
    esCelular.addEventListener('change', (e) => {
        if (e.matches && calendario.view.type === 'dayGridMonth') calendario.changeView('listWeek');
    });
}
