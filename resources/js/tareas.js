/*
 * Pantalla Tareas (lista unificada de tareas y recordatorios): check robusto, búsqueda instantánea,
 * menús desplegables y conteos vivos. Se carga solo en esta pantalla (ver tareas/index.blade.php).
 * Los modales de crear/editar viven en dialogos-tablero.js.
 *
 * El check sigue la misma regla que Hoy: cambia al instante, se bloquea mientras hay una petición en vuelo,
 * las peticiones salen de a una y la respuesta del servidor manda (si falla, la fila vuelve a lo que estaba).
 */
import { aviso } from './avisos.js';
import { crearSerie, estadoFinal, estaMarcado, marcadoDeTarea, puedeAlternar } from './hoy-lista-logica.js';
import { pedirSeguro } from './red.js';

const RETARDO_SALIDA = 300; // la fila hecha se queda un instante antes de pasar a Completadas
const DESVANECER = 180;

const raiz = document.querySelector('[data-tareas]');

if (raiz) {
    iniciar(raiz);
}

function iniciar(raiz) {
    const lista = raiz.querySelector('[data-lista]');
    const completadas = lista.querySelector('.t-grupo-completadas');
    const vacio = lista.querySelector('[data-vacio]');
    const sinResultados = lista.querySelector('[data-sin-resultados]');
    const buscador = raiz.querySelector('input[data-buscar]');
    const resumen = raiz.querySelector('[data-resumen]');
    const modoHechas = raiz.dataset.estado === 'hechas';
    const reducirMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const serie = crearSerie();

    // Las completadas que el servidor no listó (solo se muestran las más recientes) siguen contando.
    const conteoCompletadas = completadas.querySelector('[data-cuenta]');
    const ocultasCompletadas = Math.max(0, Number(conteoCompletadas.textContent) - completadas.querySelectorAll('.t-fila').length);

    /* ---------- Conteos y estados vacíos ---------- */
    const filas = (contenedor) => [...contenedor.querySelectorAll('.t-fila')];
    const consulta = () => (buscador?.value ?? '').trim().toLowerCase();

    function actualizar() {
        const q = consulta();
        let visiblesTotal = 0;
        let filasTotal = 0;

        filas(lista).forEach((fila) => { fila.hidden = q !== '' && !fila.dataset.buscar.includes(q); });

        lista.querySelectorAll('.t-grupo').forEach((grupo) => {
            const todas = filas(grupo);
            const visibles = todas.filter((fila) => !fila.hidden).length;
            const esCompletadas = grupo === completadas;
            const extra = esCompletadas && q === '' ? ocultasCompletadas : 0;

            grupo.querySelector('[data-cuenta]').textContent = visibles + extra;
            grupo.hidden = visibles + extra === 0;
            visiblesTotal += visibles;
            filasTotal += todas.length;
        });

        vacio.hidden = filasTotal > 0 || visiblesTotal > 0;
        sinResultados.hidden = !(q !== '' && filasTotal > 0 && visiblesTotal === 0);
    }

    /** Números del encabezado: se ajustan con la diferencia de cada cambio (no se recalculan con el filtro puesto). */
    function ajustarResumen(tipo, grupo, delta) {
        if (!resumen) return;

        const clave = tipo === 'tarea' ? 'tareas' : 'recordatorios';

        resumen.dataset[clave] = Math.max(0, Number(resumen.dataset[clave]) + delta);

        if (grupo === 'vencidas') {
            resumen.dataset.vencidas = Math.max(0, Number(resumen.dataset.vencidas) + delta);
        }

        const t = Number(resumen.dataset.tareas);
        const r = Number(resumen.dataset.recordatorios);
        const v = Number(resumen.dataset.vencidas);

        resumen.querySelector('[data-r-tareas]').textContent = `${t} ${t === 1 ? 'tarea' : 'tareas'}`;
        resumen.querySelector('[data-r-recordatorios]').textContent = `${r} ${r === 1 ? 'recordatorio' : 'recordatorios'}`;

        const vencidas = resumen.querySelector('[data-r-vencidas]');

        vencidas.hidden = v === 0;
        vencidas.textContent = `· ${v} ${v === 1 ? 'vencida' : 'vencidas'}`;
    }

    /* ---------- Check ---------- */
    function pintar(fila, marcada) {
        fila.querySelector('.t-check').setAttribute('aria-checked', marcada ? 'true' : 'false');
        fila.classList.toggle('es-hecha', marcada);
    }

    /** Lugar que le corresponde a la fila según su estado: Completadas si está hecha, su grupo de tiempo si no. */
    function destinoDe(fila, marcada) {
        if (marcada) return completadas.querySelector('[data-items]');

        return lista.querySelector(`[data-grupo="${fila.dataset.momento}"] [data-items]`);
    }

    function insertar(fila, contenedor, marcada) {
        const orden = Number(fila.dataset.orden);

        if (marcada || fila.dataset.orden === '') {
            marcada ? contenedor.prepend(fila) : contenedor.append(fila);

            return;
        }

        const siguiente = filas(contenedor).find((otra) => otra.dataset.orden === '' || Number(otra.dataset.orden) > orden);

        contenedor.insertBefore(fila, siguiente ?? null);
    }

    /** Pasa la fila a su lugar (una vez que ya no hay petición ni cambios pendientes). */
    function acomodar(fila) {
        const marcada = estaMarcado(fila.querySelector('.t-check').getAttribute('aria-checked'));
        const contenedor = marcada || !modoHechas ? destinoDe(fila, marcada) : null;

        if (contenedor && fila.parentElement === contenedor) return;

        const enfocada = fila.contains(document.activeElement);

        if (contenedor === null) {
            fila.remove(); // en "Hechas", lo que se desmarca deja de pertenecer a la lista
        } else {
            insertar(fila, contenedor, marcada);
            fila.classList.remove('es-saliendo');
        }

        actualizar();

        if (enfocada && fila.isConnected) fila.querySelector('.t-check').focus({ preventScroll: true });
    }

    function programarSalida(fila, inicio) {
        clearTimeout(fila.temporizador);
        fila.temporizador = setTimeout(() => {
            const marcada = estaMarcado(fila.querySelector('.t-check').getAttribute('aria-checked'));

            if (fila.querySelector('.t-check').getAttribute('aria-busy') === 'true') return;

            if (reducirMovimiento || fila.hidden) {
                acomodar(fila);

                return;
            }

            fila.classList.add('es-saliendo');
            fila.temporizador = setTimeout(() => acomodar(fila), DESVANECER);
        }, Math.max(0, RETARDO_SALIDA - (performance.now() - inicio)));
    }

    async function pedir(url, cuerpo) {
        const respuesta = await pedirSeguro(url, {
            method: 'PATCH',
            body: cuerpo ? JSON.stringify(cuerpo) : undefined,
        });
        const datos = await respuesta.json().catch(() => ({}));

        return { ok: respuesta.ok, datos };
    }

    function alternar(boton) {
        if (!puedeAlternar(boton.getAttribute('aria-busy') === 'true')) return;

        const fila = boton.closest('.t-fila');
        const esTarea = fila.dataset.tipo === 'tarea';
        const previo = estaMarcado(boton.getAttribute('aria-checked'));
        const pedido = !previo;
        const inicio = performance.now();

        clearTimeout(fila.temporizador);
        fila.classList.remove('es-saliendo');
        boton.setAttribute('aria-busy', 'true');
        pintar(fila, pedido);

        serie.agregar(async () => {
            try {
                const { ok, datos } = await pedir(
                    pedido ? boton.dataset.urlHecho : boton.dataset.urlDeshacer,
                    esTarea ? { estado: pedido ? 'completada' : 'pendiente' } : undefined,
                );

                if (!ok) throw new Error('estado');

                const servidor = esTarea ? marcadoDeTarea(datos.estado) : (typeof datos.avisado === 'boolean' ? datos.avisado : undefined);
                const marcada = estadoFinal({ previo, pedido, ok, servidor });

                pintar(fila, marcada);

                if (marcada !== previo) {
                    ajustarResumen(fila.dataset.tipo, fila.dataset.momento, marcada ? -1 : 1);
                }

                aviso.exito(esTarea
                    ? (marcada ? 'Tarea completada.' : 'Tarea vuelta a pendiente.')
                    : (marcada ? 'Recordatorio marcado como avisado.' : 'Recordatorio vuelto a pendiente.'));
                programarSalida(fila, inicio);
            } catch {
                pintar(fila, previo);
                aviso.error(esTarea ? 'No se pudo actualizar la tarea. Probá de nuevo.' : 'No se pudo actualizar el recordatorio. Probá de nuevo.');
            } finally {
                boton.removeAttribute('aria-busy');
            }
        });
    }

    lista.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.t-check');

        if (!boton) return;

        evento.preventDefault();
        alternar(boton);
    });

    /* ---------- Búsqueda instantánea ---------- */
    buscador?.addEventListener('input', actualizar);

    /* ---------- Eliminar (HTMX) ---------- */
    let vecina = null;

    document.addEventListener('htmx:beforeSwap', (evento) => {
        const fila = evento.detail.target?.closest?.('.t-fila');

        if (!fila || !evento.detail.xhr || evento.detail.xhr.status >= 400) return;

        if (!fila.classList.contains('es-hecha')) ajustarResumen(fila.dataset.tipo, fila.dataset.momento, -1);

        vecina = fila.nextElementSibling ?? fila.previousElementSibling;
    });

    // HTMX avisa sobre el elemento que reemplaza, que al borrar ya no está en el documento: se observan las listas.
    let pendiente = false;
    const observador = new MutationObserver(() => {
        if (pendiente) return;

        pendiente = true;
        requestAnimationFrame(() => {
            pendiente = false;
            actualizar();

            if (vecina?.isConnected) vecina.querySelector('.t-check')?.focus({ preventScroll: true });

            vecina = null;
        });
    });

    lista.querySelectorAll('[data-items]').forEach((items) => observador.observe(items, { childList: true }));

    /* ---------- Menús desplegables (Nueva…, proyecto) ---------- */
    const menus = () => [...document.querySelectorAll('details[data-menu]')];

    document.addEventListener('click', (evento) => {
        menus().forEach((menu) => { if (menu.open && !menu.contains(evento.target)) menu.open = false; });
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Escape') return;

        menus().filter((menu) => menu.open).forEach((menu) => {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        });
    });

    menus().forEach((menu) => menu.addEventListener('toggle', () => {
        if (menu.open) menus().forEach((otro) => { if (otro !== menu) otro.open = false; });
    }));

    actualizar();
}
