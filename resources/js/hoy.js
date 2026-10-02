/*
 * Pantalla Hoy: captura rápida (captura-rapida.js), semana seleccionable, acordeón de recomendaciones,
 * tareas y recordatorios sin recargar (fetch JSON contra las rutas existentes) y tarjeta Pomodoro
 * (hoy-pomodoro.js, que usa el motor único del temporizador).
 * Se carga solo en esta pantalla: es una entrada aparte de Vite (ver la vista hoy.blade.php).
 */
import { aviso } from './avisos.js';
import { pedirSeguro } from './red.js';
import './hoy-pomodoro.js';
import './captura-rapida.js';
import { crearSerie, estadoFinal, estaMarcado, marcadoDeTarea, pendientesFinales, puedeAlternar, textoPendientes } from './hoy-lista-logica.js';

const ETIQUETAS_EVENTO = { tarea: 'Tarea', recordatorio: 'Recordatorio', nota: 'Nota', sesion: 'Estudio', planner: 'Planner' };

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
        const punto = elemento('span', `hoy-punto tipo-${evento.tipo}`);

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
    const respuesta = await pedirSeguro(url, {
        method: metodo,
        body: cuerpo ? JSON.stringify(cuerpo) : undefined,
    });
    const datos = await respuesta.json().catch(() => ({}));

    return { ok: respuesta.ok, estado: respuesta.status, datos };
}

/* ---------- Tareas abiertas ---------- */
/*
 * Cada clic cambia la fila al instante y pide el cambio al servidor. Las peticiones salen de a una
 * (así las respuestas no se pisan), una fila con petición en curso no se puede volver a tocar, y la
 * respuesta del servidor manda: trae la lista de abiertas ya ordenada y el total de pendientes. Al marcar una
 * tarea como hecha, su fila se quita de la lista 300 ms después del clic (no hay lista de completadas). Si falla, la fila vuelve a su estado anterior.
 */
const RETARDO_SALIDA = 300;

function iniciarTareas(raiz) {
    const lista = raiz.querySelector('[data-lista]');
    const contador = raiz.querySelector('[data-pendientes]');
    const vacio = raiz.querySelector('[data-vacio]');
    const mensaje = raiz.querySelector('[data-mensaje]');
    const formulario = raiz.querySelector('[data-nueva-tarea]');
    const campo = formulario.querySelector('input');
    const boton = formulario.querySelector('button');
    const serie = crearSerie();
    let pendientes = Number(raiz.dataset.totalPendientes) || 0;

    function actualizarResumen() {
        contador.textContent = textoPendientes(pendientes);
        contador.classList.toggle('es-al-dia', pendientes <= 0);
        vacio.hidden = lista.querySelector('.hoy-item:not(.es-hecha)') !== null;
    }

    function pintarFila(fila, marcada) {
        fila.setAttribute('aria-checked', marcada ? 'true' : 'false');
        fila.closest('.hoy-item').classList.toggle('es-hecha', marcada);
    }

    /** Reemplaza la lista por la del servidor y devuelve el foco a la misma tarea. */
    function reemplazarLista(html) {
        const activa = document.activeElement;
        const idConFoco = lista.contains(activa) ? activa.closest('[data-tarea]')?.dataset.tarea : null;

        lista.innerHTML = html;

        if (idConFoco) lista.querySelector(`[data-tarea="${idConFoco}"] .hoy-fila`)?.focus({ preventScroll: true });
    }

    function aplicarRespuesta(datos, esUltima) {
        pendientes = pendientesFinales(datos.pendientes, pendientes);

        if (esUltima && typeof datos.lista === 'string') reemplazarLista(datos.lista);

        actualizarResumen();
    }

    function alternar(fila) {
        if (!puedeAlternar(fila.getAttribute('aria-busy') === 'true')) return;

        const previo = estaMarcado(fila.getAttribute('aria-checked'));
        const pedido = !previo;

        const inicio = performance.now();

        mensaje.textContent = '';
        fila.setAttribute('aria-busy', 'true');
        pintarFila(fila, pedido);

        serie.agregar(async ({ esUltima }) => {
            try {
                const { ok, datos } = await pedir(fila.dataset.url, 'PATCH', { estado: pedido ? 'completada' : 'pendiente' });

                if (!ok) throw new Error('estado');

                const marcada = estadoFinal({ previo, pedido, ok, servidor: marcadoDeTarea(datos.estado) });

                pintarFila(fila, marcada);

                if (marcada) {
                    // La fila hecha se va sola; la lista del servidor no se vuelca para no mover el resto.
                    pendientes = pendientesFinales(datos.pendientes, pendientes);
                    setTimeout(() => {
                        fila.closest('.hoy-item')?.remove();
                        actualizarResumen();
                    }, Math.max(0, RETARDO_SALIDA - (performance.now() - inicio)));
                } else {
                    aplicarRespuesta(datos, esUltima());
                }
            } catch {
                pintarFila(fila, previo);
                aviso.error('No se pudo actualizar la tarea. Probá de nuevo.');
            } finally {
                fila.removeAttribute('aria-busy');
            }
        });
    }

    lista.addEventListener('click', (evento) => {
        const fila = evento.target.closest('.hoy-fila');

        if (fila) alternar(fila);
    });

    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();

        const titulo = campo.value.trim();

        mensaje.textContent = '';

        if (titulo === '') {
            mensaje.textContent = 'Escribí un título para la tarea.';
            campo.focus();

            return;
        }

        boton.disabled = true;

        serie.agregar(async ({ esUltima }) => {
            try {
                const { ok, datos } = await pedir(formulario.action, 'POST', { titulo });

                if (!ok) {
                    // Lo que el servidor objeta de un campo queda junto al campo; una falla general, en un aviso.
                    if (datos.errors) {
                        mensaje.textContent = Object.values(datos.errors).flat().join(' ');
                    } else {
                        aviso.error('No se pudo crear la tarea. Probá de nuevo.');
                    }

                    return;
                }

                campo.value = '';
                aplicarRespuesta({ ...datos, pendientes: pendientesFinales(datos.pendientes, pendientes + 1) }, esUltima());
                aviso.exito('Tarea agregada.');
            } catch {
                aviso.error('No se pudo conectar con el servidor. Probá de nuevo.');
            } finally {
                boton.disabled = false;
                campo.focus();
            }
        });
    });
}

/* ---------- Recordatorios ---------- */
/*
 * El check alterna: avisado (atenuado y tachado, en su mismo lugar) y de vuelta a pendiente. La fila cambia al
 * instante, se bloquea mientras hay una petición en curso y vuelve atrás si falla. Nunca cambia de posición,
 * así que al desmarcar recupera su lugar por fecha; al recargar, los avisados dejan de listarse.
 */
function iniciarRecordatorios(raiz) {    const serie = crearSerie();

    function pintarFila(fila, marcada) {
        fila.setAttribute('aria-checked', marcada ? 'true' : 'false');
        fila.closest('.hoy-item').classList.toggle('es-hecha', marcada);
    }

    raiz.addEventListener('click', (evento) => {
        const fila = evento.target.closest('.hoy-fila');

        if (!fila || !puedeAlternar(fila.getAttribute('aria-busy') === 'true')) return;

        const previo = estaMarcado(fila.getAttribute('aria-checked'));
        const pedido = !previo;

        fila.setAttribute('aria-busy', 'true');
        pintarFila(fila, pedido);

        serie.agregar(async () => {
            try {
                const { ok, datos } = await pedir(pedido ? fila.dataset.urlAvisar : fila.dataset.urlReactivar, 'PATCH');

                if (!ok) throw new Error('recordatorio');

                pintarFila(fila, estadoFinal({ previo, pedido, ok, servidor: typeof datos.avisado === 'boolean' ? datos.avisado : undefined }));
                aviso.exito(pedido ? 'Recordatorio marcado como avisado.' : 'Recordatorio vuelto a pendiente.');
            } catch {
                pintarFila(fila, previo);
                aviso.error('No se pudo actualizar el recordatorio. Probá de nuevo.');
            } finally {
                fila.removeAttribute('aria-busy');
            }
        });
    });
}

/** Enlaza las tarjetas de Hoy; también las que HTMX reemplaza al guardar una captura (se marcan para no enlazarlas dos veces). */
function iniciarTarjetas() {
    const enlazar = (nodo, iniciar) => {
        if (!nodo || nodo.dataset.iniciada === '1') return;

        nodo.dataset.iniciada = '1';
        iniciar(nodo);
    };

    enlazar(document.getElementById('hoy-semana'), iniciarSemana);
    enlazar(document.querySelector('[data-acordeon]'), iniciarAcordeon);
    enlazar(document.querySelector('[data-tareas]'), iniciarTareas);
    enlazar(document.querySelector('[data-recordatorios]'), iniciarRecordatorios);
}

document.addEventListener('DOMContentLoaded', iniciarTarjetas);

document.addEventListener('htmx:afterSettle', iniciarTarjetas);
