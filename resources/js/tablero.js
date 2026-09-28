// Tablero de tareas: arrastrar y soltar nativo (sin dependencias) + botones de mover.
// Sin JS, los botones de cada tarjeta funcionan como formularios comunes.

const tablero = document.getElementById('tablero');

if (tablero) {
    const orden = JSON.parse(tablero.dataset.orden);
    const etiquetas = JSON.parse(tablero.dataset.etiquetas);
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

    const columna = (estado) => tablero.querySelector(`.tablero-columna[data-estado="${estado}"]`);

    // Contadores y estados vacíos según las tarjetas que hay realmente en cada columna.
    const recontar = () => {
        tablero.querySelectorAll('.tablero-columna').forEach((col) => {
            const cantidad = col.querySelectorAll('.tarea-tarjeta').length;
            const contador = col.querySelector('[data-contador]');
            contador.textContent = cantidad + Number(contador.dataset.ocultas || 0);
            col.querySelector('[data-vacio]').hidden = cantidad > 0;
        });
    };

    // Ajusta los botones y textos de la tarjeta al estado nuevo.
    const actualizarTarjeta = (tarjeta, estado) => {
        const posicion = orden.indexOf(estado);
        const titulo = tarjeta.dataset.titulo;
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

    // Guarda el estado con la misma ruta (PATCH + CSRF); si falla, la tarjeta vuelve a su sitio.
    const mover = async (tarjeta, estado, deshacer) => {
        const anterior = tarjeta.dataset.estado;

        if (estado === anterior) {
            return;
        }

        columna(estado).querySelector('[data-lista]').prepend(tarjeta);
        actualizarTarjeta(tarjeta, estado);
        recontar();
        tarjeta.classList.add('guardando');

        try {
            const respuesta = await fetch(tablero.dataset.urlEstado.replace('__ID__', tarjeta.dataset.id), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ estado }),
            });

            if (!respuesta.ok) {
                throw new Error(String(respuesta.status));
            }
        } catch {
            if (deshacer) {
                deshacer();
            }
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

        const boton = evento.submitter;
        const tarjeta = formulario.closest('.tarea-tarjeta');

        if (!boton || !boton.value) {
            evento.preventDefault();
            return;
        }

        evento.preventDefault();
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

    tablero.addEventListener('dragover', (evento) => {
        const col = evento.target.closest?.('.tablero-columna');

        if (arrastrada && col) {
            evento.preventDefault();
            evento.dataTransfer.dropEffect = 'move';
            tablero.querySelectorAll('.tablero-columna.sobre').forEach((otra) => otra !== col && otra.classList.remove('sobre'));
            col.classList.add('sobre');
        }
    });

    tablero.addEventListener('drop', (evento) => {
        const col = evento.target.closest?.('.tablero-columna');

        if (arrastrada && col) {
            evento.preventDefault();
            col.classList.remove('sobre');
            mover(arrastrada, col.dataset.estado);
        }
    });

    // Al borrar una tarjeta con HTMX, refresca contadores y vacíos.
    document.addEventListener('htmx:afterSettle', recontar);
}
