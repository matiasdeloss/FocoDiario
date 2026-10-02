/*
 * Tablero Kanban: arrastrar y soltar nativo (entre columnas y dentro de una columna, con el orden guardado),
 * botones de mover, alta rápida al pie de la columna y filtros por texto, proyecto y prioridad.
 * Se carga solo en esta pantalla (ver tablero/index.blade.php). Los modales viven en dialogos-tablero.js.
 * Sin JS, todo funciona con formularios comunes. Las columnas son personalizables (data-columna = id).
 *
 * Los cambios se ven al instante, se guardan de a uno (así las respuestas no se pisan) y, si el servidor
 * rechaza uno, la tarjeta vuelve a donde estaba.
 */
import { aviso } from './avisos.js';
import { crearSerie } from './hoy-lista-logica.js';
import { pedirSeguro } from './red.js';

const tablero = document.getElementById('tablero');

if (tablero) {
    const orden = JSON.parse(tablero.dataset.orden);
    const etiquetas = JSON.parse(tablero.dataset.etiquetas);
    const categorias = JSON.parse(tablero.dataset.categorias);
    const serie = crearSerie();

    const columna = (id) => tablero.querySelector(`.tablero-columna[data-columna="${id}"]`);
    const columnaDe = (tarjeta) => tarjeta.closest('.tablero-columna')?.dataset.columna;
    const tarjetasDe = (col) => [...col.querySelectorAll('[data-lista] > .tarea-tarjeta')];

    /* ---------- Filtros ---------- */
    const barra = document.querySelector('[data-filtros]');
    const filtros = { q: '', proyecto: barra?.querySelector('[data-filtro="proyecto"]')?.value ?? '', prioridad: '' };
    const hayFiltros = () => filtros.q !== '' || filtros.proyecto !== '' || filtros.prioridad !== '';

    const coincide = (tarjeta) => (filtros.q === '' || tarjeta.dataset.buscar.includes(filtros.q))
        && (filtros.proyecto === '' || tarjeta.dataset.proyecto === filtros.proyecto)
        && (filtros.prioridad === '' || tarjeta.dataset.prioridad === filtros.prioridad);

    /** Contadores, estados vacíos y resumen según las tarjetas que hay y las que dejan pasar los filtros. */
    const recontar = () => {
        let total = 0;

        tablero.querySelectorAll('.tablero-columna[data-columna]').forEach((col) => {
            const tarjetas = tarjetasDe(col);
            const visibles = tarjetas.filter((tarjeta) => !tarjeta.hidden).length;
            const contador = col.querySelector('[data-contador]');
            const ocultas = Number(contador.dataset.ocultas || 0);

            total += tarjetas.length + ocultas;
            contador.textContent = hayFiltros() ? `${visibles} de ${tarjetas.length + ocultas}` : tarjetas.length + ocultas;
            col.querySelector('[data-vacio]').hidden = tarjetas.length > 0;
        });

        const resumen = document.querySelector('[data-total]');

        if (resumen) resumen.textContent = total;
    };

    const filtrar = () => {
        tablero.querySelectorAll('.tarea-tarjeta').forEach((tarjeta) => { tarjeta.hidden = !coincide(tarjeta); });
        barra?.querySelector('[data-limpiar]')?.toggleAttribute('hidden', !hayFiltros());
        recontar();
    };

    if (barra) {
        barra.addEventListener('input', (evento) => {
            const campo = evento.target.closest('[data-filtro]');

            if (!campo) return;

            filtros[campo.dataset.filtro] = campo.dataset.filtro === 'q' ? campo.value.trim().toLowerCase() : campo.value;
            filtrar();
        });

        barra.querySelector('[data-limpiar]').addEventListener('click', () => {
            barra.querySelectorAll('[data-filtro]').forEach((campo) => { campo.value = ''; });
            Object.keys(filtros).forEach((clave) => { filtros[clave] = ''; });
            filtrar();
        });

        filtrar();
    }

    /* ---------- Tarjetas: estado visual según la columna ---------- */
    // Ajusta los botones y textos de la tarjeta a la columna nueva.
    const actualizarTarjeta = (tarjeta, id) => {
        const posicion = orden.indexOf(Number(id));
        const titulo = tarjeta.dataset.titulo;
        const estado = categorias[id];
        tarjeta.dataset.estado = estado;
        tarjeta.querySelector('[data-titulo-texto]').classList.toggle('texto-tachado', estado === 'completada');
        tarjeta.querySelector('[data-titulo-texto]').classList.toggle('fw-medium', estado !== 'completada');

        [['anterior', posicion - 1], ['siguiente', posicion + 1]].forEach(([clave, indice]) => {
            const boton = tarjeta.querySelector(`[data-mover-a="${clave}"]`);
            const destino = orden[indice];
            boton.disabled = destino === undefined;
            boton.value = destino ?? '';
            boton.title = destino ? `Mover a ${etiquetas[destino]}` : '';
            boton.setAttribute('aria-label', destino ? `Mover ${titulo} a ${etiquetas[destino]}` : `Mover ${titulo}`);
        });
    };

    /** Pone la tarjeta donde estaba: antes de su vecina anterior, o al final de su lista. */
    const devolver = (tarjeta, origen) => {
        origen.lista.insertBefore(tarjeta, origen.siguiente?.isConnected && origen.siguiente.parentElement === origen.lista ? origen.siguiente : null);
        actualizarTarjeta(tarjeta, origen.columna);
        recontar();
    };

    /**
     * Guarda la columna y el orden con PATCH + CSRF. Sale en la fila de peticiones; si falla, la tarjeta
     * vuelve a su sitio. En las columnas de tipo completada no hay orden manual (van por fecha de cierre).
     */
    const guardar = (tarjeta, destino, origen) => {
        const col = columna(destino);
        const cuerpo = { columna_id: Number(destino) };

        if (col.dataset.categoria !== 'completada') {
            cuerpo.orden = tarjetasDe(col).map((otra) => Number(otra.dataset.id));
        }

        tarjeta.classList.add('guardando');
        tarjeta.setAttribute('aria-busy', 'true');

        return serie.agregar(async () => {
            try {
                const respuesta = await pedirSeguro(tablero.dataset.urlColumna.replace('__ID__', tarjeta.dataset.id), {
                    method: 'PATCH',
                    body: JSON.stringify(cuerpo),
                });

                if (!respuesta.ok) throw new Error(String(respuesta.status));

                return true;
            } catch {
                devolver(tarjeta, origen);
                aviso.error(`No se pudo mover "${tarjeta.dataset.titulo}". Volvió a ${etiquetas[origen.columna]}.`);

                return false;
            } finally {
                tarjeta.classList.remove('guardando');
                tarjeta.removeAttribute('aria-busy');
            }
        });
    };

    const posicionActual = (tarjeta) => ({ lista: tarjeta.parentElement, siguiente: tarjeta.nextElementSibling, columna: columnaDe(tarjeta) });

    /* ---------- Botones de mover (sin arrastrar) ---------- */
    // Envío normal sin JS; con JS se mueve en el lugar (queda arriba de la columna de destino).
    tablero.addEventListener('submit', (evento) => {
        const formulario = evento.target.closest('[data-mover]');

        if (!formulario) return;

        evento.preventDefault();
        const boton = evento.submitter;
        const tarjeta = formulario.closest('.tarea-tarjeta');

        if (!boton || !boton.value || tarjeta.getAttribute('aria-busy') === 'true') return;

        const origen = posicionActual(tarjeta);
        const clave = boton.dataset.moverA;

        columna(boton.value).querySelector('[data-lista]').prepend(tarjeta);
        actualizarTarjeta(tarjeta, boton.value);
        recontar();

        guardar(tarjeta, boton.value, origen).then(() => {
            const activo = tarjeta.querySelector(`[data-mover-a="${clave}"]:not(:disabled)`)
                ?? tarjeta.querySelector('[data-mover-a]:not(:disabled)');
            (activo ?? tarjeta).focus?.();
        });
    });

    /* ---------- Arrastrar y soltar ---------- */
    let arrastrada = null;
    let origenArrastre = null;
    let soltada = false;

    tablero.addEventListener('dragstart', (evento) => {
        const tarjeta = evento.target.closest?.('.tarea-tarjeta');

        if (!tarjeta) return;

        if (tarjeta.getAttribute('aria-busy') === 'true') {
            evento.preventDefault();

            return;
        }

        arrastrada = tarjeta;
        origenArrastre = posicionActual(tarjeta);
        soltada = false;
        evento.dataTransfer.effectAllowed = 'move';
        evento.dataTransfer.setData('text/plain', tarjeta.dataset.id);
        requestAnimationFrame(() => { if (arrastrada === tarjeta) tarjeta.classList.add('arrastrando'); });
    });

    tablero.addEventListener('dragend', () => {
        if (arrastrada && !soltada) {
            // Se soltó fuera de una columna (o con Esc): todo vuelve a como estaba.
            devolver(arrastrada, origenArrastre);
        }

        arrastrada?.classList.remove('arrastrando');
        arrastrada = null;
        origenArrastre = null;
        tablero.querySelectorAll('.tablero-columna.sobre').forEach((col) => col.classList.remove('sobre'));
    });

    const columnaDestino = (evento) => evento.target.closest?.('.tablero-columna[data-columna]');

    /** Tarjeta antes de la que hay que insertar según la altura del cursor (null = al final). */
    const tarjetaSiguiente = (lista, y) => tarjetasDe(lista.closest('.tablero-columna'))
        .filter((otra) => otra !== arrastrada && !otra.hidden)
        .find((otra) => {
            const caja = otra.getBoundingClientRect();

            return y < caja.top + caja.height / 2;
        }) ?? null;

    tablero.addEventListener('dragover', (evento) => {
        const col = columnaDestino(evento);

        if (!arrastrada || !col) return;

        evento.preventDefault();
        evento.dataTransfer.dropEffect = 'move';
        tablero.querySelectorAll('.tablero-columna.sobre').forEach((otra) => otra !== col && otra.classList.remove('sobre'));
        col.classList.add('sobre');

        const lista = col.querySelector('[data-lista]');
        // En las columnas de completadas la tarjeta siempre entra arriba (van por fecha de cierre).
        const siguiente = col.dataset.categoria === 'completada'
            ? tarjetasDe(col).find((otra) => otra !== arrastrada) ?? null
            : tarjetaSiguiente(lista, evento.clientY);

        if (arrastrada.parentElement !== lista || arrastrada.nextElementSibling !== siguiente) {
            lista.insertBefore(arrastrada, siguiente);
            recontar();
        }
    });

    tablero.addEventListener('drop', (evento) => {
        const col = columnaDestino(evento);

        if (!arrastrada || !col) return;

        evento.preventDefault();
        soltada = true;
        col.classList.remove('sobre');

        const tarjeta = arrastrada;
        const origen = origenArrastre;
        const destino = col.dataset.columna;
        const igual = origen.lista === tarjeta.parentElement && origen.siguiente === tarjeta.nextElementSibling;

        if (igual) return;

        actualizarTarjeta(tarjeta, destino);
        recontar();
        guardar(tarjeta, destino, origen);
    });

    /* ---------- Alta rápida al pie de la columna ---------- */
    tablero.querySelectorAll('[data-anadir]').forEach((zona) => {
        const abrir = zona.querySelector('[data-anadir-abrir]');
        const formulario = zona.querySelector('[data-anadir-form]');
        const campo = formulario.elements.titulo;
        const error = zona.querySelector('[data-anadir-error]');
        const enviar = zona.querySelector('[data-anadir-enviar]');
        const lista = zona.closest('.tablero-columna').querySelector('[data-lista]');

        const cerrar = ({ enfocar = true } = {}) => {
            formulario.hidden = true;
            abrir.hidden = false;
            campo.value = '';
            error.textContent = '';

            if (enfocar) abrir.focus();
        };

        abrir.addEventListener('click', () => {
            abrir.hidden = true;
            formulario.hidden = false;
            campo.focus();
        });

        zona.querySelector('[data-anadir-cerrar]').addEventListener('click', () => cerrar());

        campo.addEventListener('keydown', (evento) => {
            if (evento.key === 'Enter' && !evento.shiftKey) {
                evento.preventDefault();
                formulario.requestSubmit();
            } else if (evento.key === 'Escape') {
                evento.stopPropagation();
                cerrar();
            }
        });

        formulario.addEventListener('submit', async (evento) => {
            evento.preventDefault();

            const titulo = campo.value.trim();

            if (titulo === '') {
                error.textContent = 'Escribí un título para la tarjeta.';
                campo.focus();

                return;
            }

            if (enviar.disabled) return;

            enviar.disabled = true;
            error.textContent = '';

            try {
                const respuesta = await pedirSeguro(zona.dataset.url, {
                    method: 'POST',
                    body: JSON.stringify({ titulo }),
                });
                const datos = await respuesta.json().catch(() => ({}));

                if (respuesta.status === 422) {
                    error.textContent = Object.values(datos.errors ?? {}).flat().join(' ') || 'El título no es válido.';
                    campo.focus();

                    return;
                }

                if (!respuesta.ok) throw new Error(String(respuesta.status));

                lista.insertAdjacentHTML('beforeend', datos.html);
                const nueva = lista.lastElementChild;

                if (nueva) window.htmx?.process(nueva); // activa el borrado con HTMX de la tarjeta nueva
                if (nueva && !coincide(nueva)) nueva.hidden = true;

                campo.value = '';
                recontar();
                campo.focus(); // sigue abierto para cargar la siguiente
            } catch {
                error.textContent = 'No se pudo añadir. Revisá tu conexión e intentá de nuevo.';
            } finally {
                enviar.disabled = false;
            }
        });
    });

    /* ---------- Menús y detalles ---------- */
    // Al abrir "Añadir ..." (sin JS), el foco va al campo; Escape cierra el menú abierto.
    tablero.addEventListener('toggle', (evento) => {
        if (evento.target.matches('details.tablero-anadir') && evento.target.open) {
            evento.target.querySelector('input')?.focus();
        }

        // Un solo menú de columna abierto a la vez.
        if (evento.target.matches('details.tablero-menu') && evento.target.open) {
            tablero.querySelectorAll('details.tablero-menu[open]').forEach((otro) => { if (otro !== evento.target) otro.open = false; });
        }
    }, true);

    document.addEventListener('click', (evento) => {
        tablero.querySelectorAll('details.tablero-menu[open]').forEach((menu) => { if (!menu.contains(evento.target)) menu.open = false; });
    });

    // Clic en la tarjeta (fuera de sus botones): se abre para editarla (comentario, color…). Usa el mismo
    // enlace "Editar" de la tarjeta, así el modal recibe sus datos. Arrastrar no dispara el clic.
    tablero.addEventListener('click', (evento) => {
        const tarjeta = evento.target.closest('.tarea-tarjeta');

        if (!tarjeta || evento.target.closest('a, button, form, input, select, textarea, label, summary')) return;
        if (window.getSelection()?.toString()) return;

        tarjeta.querySelector('[data-abrir-tarea="editar"]')?.click();
    });

    tablero.addEventListener('keydown', (evento) => {
        const abierto = evento.key === 'Escape' && evento.target.closest?.('details[open]');

        if (abierto) {
            abierto.open = false;
            abierto.querySelector('summary')?.focus();
        }
    });

    // Al borrar una tarjeta con HTMX (o añadir una), refresca contadores y vacíos. HTMX avisa sobre el elemento
    // que reemplaza, que ya no está en el documento al borrar: por eso se observan las listas.
    let pendiente = false;
    const observador = new MutationObserver(() => {
        if (pendiente) return;

        pendiente = true;
        requestAnimationFrame(() => { pendiente = false; recontar(); });
    });

    tablero.querySelectorAll('[data-lista]').forEach((lista) => observador.observe(lista, { childList: true }));
}
