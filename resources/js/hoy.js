/*
 * Pantalla Hoy: nota rápida (color y contador), semana seleccionable, acordeón de recomendaciones,
 * tareas y recordatorios sin recargar (fetch JSON contra las rutas existentes) y tarjeta Pomodoro
 * (hoy-pomodoro.js, que usa el motor único del temporizador).
 * Se carga solo en esta pantalla: es una entrada aparte de Vite (ver la vista hoy.blade.php).
 */
import { encabezados } from './pomodoro-motor.js';
import './hoy-pomodoro.js';

const ETIQUETAS_EVENTO = { tarea: 'Tarea', recordatorio: 'Recordatorio', nota: 'Nota', sesion: 'Estudio' };

/* ---------- Nota rápida ---------- */
// La sección se reemplaza entera con HTMX al guardar: por eso los eventos se delegan en el documento.
function textoContador(cantidad) {
    return `${cantidad} ${cantidad === 1 ? 'carácter' : 'caracteres'}`;
}

function fijarColor(seccion, color) {
    const valor = seccion.querySelector('[data-nota-color-valor]');
    const texto = seccion.querySelector('.hoy-nota-texto');

    valor.value = color;
    seccion.querySelectorAll('[data-color-nota]').forEach((boton) => {
        boton.setAttribute('aria-pressed', boton.dataset.colorNota === color ? 'true' : 'false');
    });

    if (color) texto.dataset.color = color;
    else delete texto.dataset.color;
}

function actualizarContador(seccion) {
    const texto = seccion.querySelector('.hoy-nota-texto');

    seccion.querySelector('[data-nota-contador]').textContent = textoContador(Array.from(texto.value).length);
}

document.addEventListener('input', (evento) => {
    const seccion = evento.target.closest?.('[data-nota-rapida]');

    if (seccion && evento.target.matches('.hoy-nota-texto')) actualizarContador(seccion);
});

document.addEventListener('click', (evento) => {
    const seccion = evento.target.closest?.('[data-nota-rapida]');

    if (!seccion) return;

    const color = evento.target.closest('[data-color-nota]');

    if (color) {
        // Volver a tocar el color elegido lo quita.
        const actual = seccion.querySelector('[data-nota-color-valor]').value;

        fijarColor(seccion, actual === color.dataset.colorNota ? '' : color.dataset.colorNota);

        return;
    }

    if (evento.target.closest('[data-nota-limpiar]')) {
        const texto = seccion.querySelector('.hoy-nota-texto');

        texto.value = '';
        fijarColor(seccion, '');
        actualizarContador(seccion);
        texto.focus();
    }
});

// Ctrl+Enter (o Cmd+Enter) guarda sin salir del teclado.
document.addEventListener('keydown', (evento) => {
    if (evento.key === 'Enter' && (evento.ctrlKey || evento.metaKey) && evento.target.matches?.('.hoy-nota-texto')) {
        evento.preventDefault();
        evento.target.form.requestSubmit();
    }
});

/* ---------- Esta semana ---------- */
function iniciarSemana(raiz) {
    const dias = JSON.parse(raiz.dataset.semana);
    const urlCalendario = raiz.dataset.urlCalendario;
    const contenedor = raiz.querySelector('[data-eventos]');
    const botones = [...raiz.querySelectorAll('[data-fecha]')];

    function elemento(etiqueta, clase, texto) {
        const nodo = document.createElement(etiqueta);

        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;

        return nodo;
    }

    function dibujarEvento(evento) {
        const enlace = elemento('a', 'hoy-evento');
        const titulo = elemento('span', 'hoy-evento-titulo');
        const punto = elemento('span', `hoy-punto hoy-punto-${evento.tipo}`);

        enlace.href = `${urlCalendario}?fecha=${evento.fecha}`;
        punto.setAttribute('aria-hidden', 'true');
        titulo.append(
            punto,
            elemento('span', 'hoy-solo-lector', `${ETIQUETAS_EVENTO[evento.tipo] ?? ''}: `),
            elemento('span', evento.hecho ? 'hoy-tachado' : '', evento.titulo),
        );
        enlace.append(titulo, elemento('span', 'hoy-evento-hora', evento.hora ?? evento.detalle ?? ''));

        return enlace;
    }

    function seleccionar(fecha) {
        const dia = dias.find((d) => d.fecha === fecha);

        botones.forEach((boton) => boton.setAttribute('aria-pressed', boton.dataset.fecha === fecha ? 'true' : 'false'));
        contenedor.replaceChildren(...(dia.eventos.length > 0
            ? dia.eventos.map((evento) => dibujarEvento({ ...evento, fecha }))
            : [elemento('p', 'hoy-vacio', 'Nada agendado este día.')]));
    }

    botones.forEach((boton) => boton.addEventListener('click', () => seleccionar(boton.dataset.fecha)));
}

/* ---------- Recomendaciones: acordeón (se abre una a la vez) ---------- */
function iniciarAcordeon(raiz) {
    raiz.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.hoy-rec-boton');

        if (!boton) return;

        const abrir = boton.getAttribute('aria-expanded') !== 'true';

        raiz.querySelectorAll('.hoy-rec-boton').forEach((otro) => {
            const abierto = otro === boton && abrir;

            otro.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            document.getElementById(otro.getAttribute('aria-controls')).hidden = !abierto;
            otro.closest('.hoy-rec').classList.toggle('es-abierta', abierto);
        });
    });
}

/* ---------- Peticiones JSON ---------- */
async function pedir(url, metodo, cuerpo) {
    const respuesta = await fetch(url, {
        method: metodo,
        headers: encabezados(),
        body: cuerpo ? JSON.stringify(cuerpo) : undefined,
    });
    const datos = await respuesta.json().catch(() => ({}));

    return { ok: respuesta.ok, estado: respuesta.status, datos };
}

/* ---------- Tareas abiertas ---------- */
function iniciarTareas(raiz) {
    const lista = raiz.querySelector('[data-lista]');
    const contador = raiz.querySelector('[data-pendientes]');
    const vacio = raiz.querySelector('[data-vacio]');
    const mensaje = raiz.querySelector('[data-mensaje]');
    const formulario = raiz.querySelector('[data-nueva-tarea]');
    const campo = formulario.querySelector('input');
    const COMPLETADAS_VISIBLES = 3;
    let pendientes = Number(raiz.dataset.totalPendientes) || 0;

    function actualizarResumen() {
        contador.textContent = `${pendientes} ${pendientes === 1 ? 'pendiente' : 'pendientes'}`;
        vacio.hidden = lista.querySelector('.hoy-item:not(.es-hecha)') !== null;
    }

    /** Las abiertas van arriba y las completadas (las últimas 3, la más reciente primero) al final. */
    function colocar(item) {
        const primeraHecha = [...lista.children].find((otro) => otro !== item && otro.classList.contains('es-hecha'));

        lista.insertBefore(item, primeraHecha ?? null);
        [...lista.querySelectorAll('.es-hecha')].slice(COMPLETADAS_VISIBLES).forEach((sobrante) => sobrante.remove());
    }

    async function alternar(fila) {
        if (fila.getAttribute('aria-busy') === 'true') return;

        const item = fila.closest('.hoy-item');
        const completar = fila.getAttribute('aria-checked') !== 'true';

        mensaje.textContent = '';
        fila.setAttribute('aria-busy', 'true');

        try {
            const { ok } = await pedir(fila.dataset.url, 'PATCH', { estado: completar ? 'completada' : 'pendiente' });

            if (!ok) throw new Error('estado');

            const teniaFoco = document.activeElement === fila;

            fila.setAttribute('aria-checked', completar ? 'true' : 'false');
            item.classList.toggle('es-hecha', completar);
            pendientes = Math.max(0, pendientes + (completar ? -1 : 1));
            colocar(item);
            actualizarResumen();

            if (teniaFoco) fila.focus();
        } catch {
            mensaje.textContent = 'No se pudo actualizar la tarea. Probá de nuevo.';
        } finally {
            fila.removeAttribute('aria-busy');
        }
    }

    lista.addEventListener('click', (evento) => {
        const fila = evento.target.closest('.hoy-fila');

        if (fila) alternar(fila);
    });

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        const titulo = campo.value.trim();

        mensaje.textContent = '';

        if (titulo === '') {
            mensaje.textContent = 'Escribí un título para la tarea.';
            campo.focus();

            return;
        }

        formulario.querySelector('button').disabled = true;

        try {
            const { ok, datos } = await pedir(formulario.action, 'POST', { titulo });

            if (!ok) {
                mensaje.textContent = datos.errors ? Object.values(datos.errors).flat().join(' ') : 'No se pudo crear la tarea. Probá de nuevo.';

                return;
            }

            const plantilla = document.createElement('template');

            plantilla.innerHTML = datos.html.trim();
            colocar(plantilla.content.firstElementChild);
            pendientes += 1;
            campo.value = '';
            actualizarResumen();
            mensaje.textContent = 'Tarea agregada.';
        } catch {
            mensaje.textContent = 'No se pudo conectar con el servidor. Probá de nuevo.';
        } finally {
            formulario.querySelector('button').disabled = false;
            campo.focus();
        }
    });
}

/* ---------- Recordatorios ---------- */
function iniciarRecordatorios(raiz) {
    const mensaje = raiz.querySelector('[data-mensaje]');

    raiz.addEventListener('click', async (evento) => {
        const fila = evento.target.closest('.hoy-fila');

        if (!fila || fila.getAttribute('aria-disabled') === 'true' || fila.getAttribute('aria-busy') === 'true') return;

        mensaje.textContent = '';
        fila.setAttribute('aria-busy', 'true');

        try {
            const { ok } = await pedir(fila.dataset.url, 'PATCH');

            if (!ok) throw new Error('avisar');

            fila.setAttribute('aria-checked', 'true');
            fila.setAttribute('aria-disabled', 'true');
            mensaje.textContent = 'Recordatorio marcado como avisado.';
        } catch {
            mensaje.textContent = 'No se pudo marcar el recordatorio. Probá de nuevo.';
        } finally {
            fila.removeAttribute('aria-busy');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const semana = document.getElementById('hoy-semana');
    const acordeon = document.querySelector('[data-acordeon]');
    const tareas = document.querySelector('[data-tareas]');
    const recordatorios = document.querySelector('[data-recordatorios]');

    if (semana) iniciarSemana(semana);
    if (acordeon) iniciarAcordeon(acordeon);
    if (tareas) iniciarTareas(tareas);
    if (recordatorios) iniciarRecordatorios(recordatorios);
});
