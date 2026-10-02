/*
 * Campos de "Editar completo" del calendario, que se editan en el lugar (sin ir a otra pantalla):
 *  - tarea: proyecto, prioridad y estado;
 *  - nota: materia, color y fijada;
 *  - recordatorio: tarea vinculada.
 * Los usa el panel de detalle (que ya muestra prioridad y color por su cuenta) y cada tarjeta del panel "Por ubicar".
 * Se dibujan en una grilla de dos columnas: proyecto y estado van de a pares; el resto, a lo ancho.
 * `guardar(cambio)` manda el cambio al servidor; quien llama vuelve a pintar con los datos que devuelve.
 */

let secuencia = 0;

function campo(etiqueta, control, id) {
    const envoltura = document.createElement('div');
    const rotulo = document.createElement(control.tagName === 'DIV' ? 'span' : 'label');

    envoltura.className = 'detalle-campo';
    rotulo.className = 'detalle-etiqueta';
    rotulo.textContent = etiqueta;

    if (control.tagName === 'DIV') {
        rotulo.id = `${id}-etiqueta`;
        control.setAttribute('aria-labelledby', rotulo.id);
    } else {
        rotulo.htmlFor = id;
        control.id = id;
    }

    envoltura.append(rotulo, control);

    return envoltura;
}

function selector(opciones, actual, vacio, alCambiar) {
    const select = document.createElement('select');

    select.className = 'form-select form-select-sm';

    if (vacio !== null) select.append(new Option(vacio, ''));
    opciones.forEach((opcion) => select.append(new Option(opcion.etiqueta, opcion.valor)));
    select.value = actual == null ? '' : String(actual);
    select.addEventListener('change', () => alCambiar(select.value === '' ? null : select.value));

    return select;
}

/** `soloPunto`: grupo de colores; cada opción es un punto redondo y su nombre queda en aria-label/title. */
function botonesDeOpcion(opciones, actual, alElegir, soloPunto = false) {
    const grupo = document.createElement('div');

    grupo.className = 'detalle-opciones';
    grupo.setAttribute('role', 'radiogroup');

    opciones.forEach((opcion) => {
        const boton = document.createElement('button');

        boton.type = 'button';
        boton.className = soloPunto ? 'detalle-opcion detalle-opcion-color' : 'detalle-opcion';
        boton.setAttribute('role', 'radio');
        boton.setAttribute('aria-checked', String(opcion.valor === actual));
        boton.dataset.valor = opcion.valor;

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
        boton.addEventListener('click', () => {
            if (opcion.valor !== actual) alElegir(opcion.valor);
        });
        grupo.append(boton);
    });

    return grupo;
}

/**
 * Dibuja los campos extra de `d` (datos de /calendario/detalle) dentro de `contenedor`.
 * Conserva el foco en el mismo campo al volver a pintar después de guardar.
 * @param {HTMLElement} contenedor
 * @param {object} d
 * @param {(cambio: object) => Promise<unknown>} guardar
 * @param {{ conPrioridadYColor?: boolean, reactivar?: ((boton: HTMLButtonElement) => void) | null }} [opciones]
 *   `reactivar`: si viene, un recordatorio ya avisado ofrece "Marcar como no avisado" (recibe el botón para su estado ocupado).
 */
export function pintarCamposExtra(contenedor, d, guardar, { conPrioridadYColor = false, reactivar = null } = {}) {
    const conFoco = contenedor.contains(document.activeElement) ? document.activeElement.closest('[data-campo-extra]')?.dataset.campoExtra : null;
    const base = `extra-${++secuencia}`;
    const campos = [];
    const agregar = (nombre, elemento) => {
        elemento.dataset.campoExtra = nombre;
        campos.push(elemento);
    };

    if (d.tipo === 'tarea') {
        if (conPrioridadYColor) {
            agregar('prioridad', campo('Prioridad', botonesDeOpcion(d.prioridades ?? [], d.prioridad, (valor) => guardar({ prioridad: valor })), `${base}-prioridad`));
        }

        const proyecto = document.createElement('input');
        const lista = document.createElement('datalist');

        lista.id = `${base}-proyectos`;
        (d.proyectos ?? []).forEach((nombre) => lista.append(new Option(nombre)));
        proyecto.type = 'text';
        proyecto.className = 'form-control form-control-sm';
        proyecto.maxLength = 255;
        proyecto.placeholder = 'Sin proyecto';
        proyecto.value = d.proyecto ?? '';
        proyecto.setAttribute('list', lista.id);
        proyecto.autocomplete = 'off';
        proyecto.addEventListener('change', () => {
            const valor = proyecto.value.trim();

            if (valor !== (d.proyecto ?? '')) guardar({ proyecto: valor === '' ? null : valor });
        });
        proyecto.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); proyecto.blur(); }
        });

        const envoltura = campo('Proyecto', proyecto, `${base}-proyecto`);

        envoltura.append(lista);
        agregar('proyecto', envoltura);

        agregar('estado', campo('Estado', selector(d.estados ?? [], d.estado, null, (valor) => guardar({ estado: valor })), `${base}-estado`));
    }

    if (d.tipo === 'nota') {
        if (conPrioridadYColor) {
            const colores = [{ valor: null, etiqueta: 'Sin color' }, ...(d.colores ?? []).map((c) => ({ valor: c.valor, etiqueta: c.etiqueta, marca: c.marca }))];

            agregar('color', campo('Color', botonesDeOpcion(colores, d.color ?? null, (valor) => guardar({ color: valor }), true), `${base}-color`));
        }

        agregar('materia', campo('Materia', selector(d.destinos ?? [], d.contexto_id, 'Bandeja de entrada',
            (valor) => guardar({ contexto_id: valor === null ? null : Number(valor) })), `${base}-materia`));

        const fijada = document.createElement('label');
        const casilla = document.createElement('input');

        fijada.className = 'extra-casilla';
        casilla.type = 'checkbox';
        casilla.className = 'form-check-input';
        casilla.checked = Boolean(d.fijada);
        casilla.addEventListener('change', () => guardar({ fijada: casilla.checked }));
        fijada.append(casilla, ' Fijada arriba en Notas');
        agregar('fijada', fijada);
    }

    if (d.tipo === 'recordatorio') {
        agregar('tarea', campo('Tarea vinculada', selector(d.tareas ?? [], d.tarea_id, 'Sin tarea',
            (valor) => guardar({ tarea_id: valor === null ? null : Number(valor) })), `${base}-tarea`));
    }

    if (d.tipo === 'recordatorio' && d.avisado && reactivar) {
        const boton = document.createElement('button');

        boton.type = 'button';
        boton.className = 'btn btn-sm btn-foco-suave detalle-boton';
        boton.innerHTML = '<i class="bi bi-bell" aria-hidden="true"></i> Marcar como no avisado';
        boton.addEventListener('click', () => reactivar(boton));
        agregar('reactivar', boton);
    }

    contenedor.replaceChildren(...campos);

    if (conFoco) contenedor.querySelector(`[data-campo-extra="${conFoco}"] :is(input, select, [aria-checked="true"], button)`)?.focus({ preventScroll: true });
}
