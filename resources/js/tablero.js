// Tablero de tareas: arrastrar y soltar nativo (sin dependencias) + botones de mover.
// Las columnas son personalizables (data-columna = id). Sin JS, todo funciona con formularios comunes.

const tablero = document.getElementById('tablero');

if (tablero) {
    const orden = JSON.parse(tablero.dataset.orden);
    const etiquetas = JSON.parse(tablero.dataset.etiquetas);
    const categorias = JSON.parse(tablero.dataset.categorias);
    const aviso = document.getElementById('tablero-aviso');
    const token = () => document.querySelector('meta[name="csrf-token"]')?.content;
    let arrastrada = null;
    let temporizadorAviso;

    const mostrarAviso = (texto) => {
        aviso.textContent = texto;
        aviso.hidden = false;
        clearTimeout(temporizadorAviso);
        temporizadorAviso = setTimeout(() => { aviso.hidden = true; }, 6000);
    };

    const columna = (id) => tablero.querySelector(`.tablero-columna[data-columna="${id}"]`);
    const columnaDe = (tarjeta) => tarjeta.closest('.tablero-columna')?.dataset.columna;

    // Contadores y estados vacíos según las tarjetas que hay realmente en cada columna.
    const recontar = () => {
        tablero.querySelectorAll('.tablero-columna[data-columna]').forEach((col) => {
            const cantidad = col.querySelectorAll('.tarea-tarjeta').length;
            const contador = col.querySelector('[data-contador]');
            contador.textContent = cantidad + Number(contador.dataset.ocultas || 0);
            col.querySelector('[data-vacio]').hidden = cantidad > 0;
        });
    };

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

    // Guarda la columna con PATCH + CSRF; si falla, la tarjeta vuelve a su sitio.
    const mover = async (tarjeta, id) => {
        const anterior = columnaDe(tarjeta);

        if (String(id) === String(anterior)) {
            return;
        }

        columna(id).querySelector('[data-lista]').prepend(tarjeta);
        actualizarTarjeta(tarjeta, id);
        recontar();
        tarjeta.classList.add('guardando');

        try {
            const respuesta = await fetch(tablero.dataset.urlColumna.replace('__ID__', tarjeta.dataset.id), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ columna_id: Number(id) }),
            });

            if (!respuesta.ok) {
                throw new Error(String(respuesta.status));
            }
        } catch {
            columna(anterior).querySelector('[data-lista]').prepend(tarjeta);
            actualizarTarjeta(tarjeta, anterior);
            recontar();
            mostrarAviso(`No se pudo mover "${tarjeta.dataset.titulo}". Volvió a ${etiquetas[anterior]}.`);
        } finally {
            tarjeta.classList.remove('guardando');
        }
    };

    // Botones de mover: envío normal sin JS; con JS se mueve en el lugar.
    tablero.addEventListener('submit', (evento) => {
        const formulario = evento.target.closest('[data-mover]');

        if (!formulario) {
            return;
        }

        evento.preventDefault();
        const boton = evento.submitter;
        const tarjeta = formulario.closest('.tarea-tarjeta');

        if (!boton || !boton.value) {
            return;
        }

        const clave = boton.dataset.moverA;
        mover(tarjeta, boton.value).then(() => {
            const activo = tarjeta.querySelector(`[data-mover-a="${clave}"]:not(:disabled)`)
                ?? tarjeta.querySelector('[data-mover-a]:not(:disabled)');
            (activo ?? tarjeta).focus?.();
        });
    });

    // Arrastrar y soltar.
    tablero.addEventListener('dragstart', (evento) => {
        const tarjeta = evento.target.closest?.('.tarea-tarjeta');

        if (!tarjeta) {
            return;
        }

        arrastrada = tarjeta;
        evento.dataTransfer.effectAllowed = 'move';
        evento.dataTransfer.setData('text/plain', tarjeta.dataset.id);
        requestAnimationFrame(() => tarjeta.classList.add('arrastrando'));
    });

    tablero.addEventListener('dragend', () => {
        arrastrada?.classList.remove('arrastrando');
        arrastrada = null;
        tablero.querySelectorAll('.tablero-columna.sobre').forEach((col) => col.classList.remove('sobre'));
    });

    const columnaDestino = (evento) => evento.target.closest?.('.tablero-columna[data-columna]');

    tablero.addEventListener('dragover', (evento) => {
        const col = columnaDestino(evento);

        if (arrastrada && col) {
            evento.preventDefault();
            evento.dataTransfer.dropEffect = 'move';
            tablero.querySelectorAll('.tablero-columna.sobre').forEach((otra) => otra !== col && otra.classList.remove('sobre'));
            col.classList.add('sobre');
        }
    });

    tablero.addEventListener('drop', (evento) => {
        const col = columnaDestino(evento);

        if (arrastrada && col) {
            evento.preventDefault();
            col.classList.remove('sobre');
            mover(arrastrada, col.dataset.columna);
        }
    });

    // Al abrir "Añadir ...", el foco va al campo; Escape lo cierra.
    tablero.addEventListener('toggle', (evento) => {
        if (evento.target.matches('details.tablero-anadir') && evento.target.open) {
            evento.target.querySelector('input')?.focus();
        }
    }, true);

    tablero.addEventListener('keydown', (evento) => {
        const abierto = evento.key === 'Escape' && evento.target.closest?.('details[open]');

        if (abierto) {
            abierto.open = false;
            abierto.querySelector('summary')?.focus();
        }
    });

    // Al borrar una tarjeta con HTMX, refresca contadores y vacíos.
    document.addEventListener('htmx:afterSettle', recontar);
}
