/*
 * Editor de las cajas de la Agenda: título, texto con renglones, listas con casillas, actividad, horas y "hecha".
 * Cada caja es un <article data-caja data-url data-metodo data-estado='{json}'>; los cambios van al guardador
 * (autoguardado). Lo usan la hoja del día (PATCH por caja) y las cajas Notas y Pendiente del planner (PUT).
 */
import {
    alternarItem, cambiarTextoItem, insertarItemDespues, listaATexto, normalizarItems, quitarItem, textoALista,
} from './agenda-logica.js';

const CLASE_ACTIVIDAD = /(^|\s)actividad-[\w-]+/g;

const PLANTILLA_AGREGAR = '<button type="button" class="caja-agregar" data-accion="agregar-item"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar ítem</button>';

export function crearEditorDeCajas({ guardador, clasesActividad = {} }) {
    const controladores = new WeakMap();

    function filaItem(item) {
        const fila = document.getElementById('agenda-plantilla-item').content.firstElementChild.cloneNode(true);

        fila.querySelector('[data-item-texto]').value = item.texto;
        fila.querySelector('[data-item-check]').checked = item.hecho;
        fila.classList.toggle('es-tildado', item.hecho);

        return fila;
    }

    function iniciar(articulo) {
        if (controladores.has(articulo)) {
            return controladores.get(articulo);
        }

        const estado = JSON.parse(articulo.dataset.estado);
        const metodo = articulo.dataset.metodo ?? 'PATCH';
        const clave = `${metodo} ${articulo.dataset.url}`;
        const hoja = () => articulo.querySelector('[data-hoja]');

        estado.items = normalizarItems(estado.items);

        function guardar(parche) {
            Object.assign(estado, parche);
            // Las cajas de la semana se guardan enteras (el servidor las crea la primera vez que se escribe en ellas).
            const cuerpo = metodo === 'PUT'
                ? { tipo: estado.tipo, contenido: estado.contenido ?? null, items: estado.items }
                : parche;

            guardador.programar(clave, cuerpo);
            articulo.dispatchEvent(new CustomEvent('caja:cambio', { bubbles: true, detail: { parche } }));
        }

        /* ---------- Cómo se ve ---------- */

        function pintarActividad() {
            articulo.className = articulo.className.replace(CLASE_ACTIVIDAD, '');

            const clase = estado.contexto_id ? clasesActividad[estado.contexto_id] : null;

            if (clase) {
                articulo.classList.add(clase);
            }
        }

        function pintarBorde() {
            const grosor = Number(estado.borde_grosor) || 1;

            articulo.style.setProperty('--caja-borde-grosor', `${grosor}px`);

            if (estado.borde_color) {
                articulo.style.setProperty('--caja-borde-color', estado.borde_color);
            } else {
                articulo.style.removeProperty('--caja-borde-color');
            }
        }

        function pintarHora() {
            const marca = articulo.querySelector('[data-hora]');

            if (!marca) {
                return;
            }

            const texto = estado.hora_inicio ? (estado.hora_fin ? `${estado.hora_inicio}–${estado.hora_fin}` : estado.hora_inicio) : '';

            marca.textContent = texto;
            marca.hidden = texto === '';
        }

        function pintarHecha() {
            articulo.classList.toggle('es-hecha', Boolean(estado.hecha));

            const boton = articulo.querySelector('[data-accion="hecha"]');

            if (boton) {
                boton.setAttribute('aria-pressed', estado.hecha ? 'true' : 'false');
                boton.setAttribute('aria-label', estado.hecha ? 'Marcar como pendiente' : 'Marcar como hecha');
                boton.title = estado.hecha ? 'Marcar como pendiente' : 'Marcar como hecha';
            }
        }

        function pintarSelectorDeTipo() {
            articulo.querySelectorAll('[data-tipo]').forEach((boton) => {
                boton.setAttribute('aria-pressed', boton.dataset.tipo === estado.tipo ? 'true' : 'false');
            });
        }

        function construirCuerpo() {
            const cuerpo = hoja();
            const etiqueta = articulo.dataset.etiquetaContenido ?? 'Contenido de la caja';

            cuerpo.replaceChildren();
            cuerpo.dataset.tipo = estado.tipo;

            if (estado.tipo === 'lista') {
                const lista = document.createElement('ul');

                lista.className = 'caja-items';
                lista.dataset.items = '';
                lista.setAttribute('aria-label', etiqueta);
                estado.items.forEach((item) => lista.append(filaItem(item)));

                cuerpo.append(lista);
                cuerpo.insertAdjacentHTML('beforeend', PLANTILLA_AGREGAR);
            } else {
                const texto = document.createElement('textarea');

                texto.className = 'caja-texto';
                texto.dataset.campo = 'contenido';
                texto.setAttribute('aria-label', etiqueta);
                texto.placeholder = articulo.dataset.marcaDeTexto ?? 'Escribí acá…';
                texto.value = estado.contenido ?? '';

                cuerpo.append(texto);
            }
        }

        function enfocarItem(indice) {
            const campos = articulo.querySelectorAll('[data-item-texto]');
            const campo = campos[Math.min(Math.max(indice, 0), campos.length - 1)];

            if (campo) {
                campo.focus();
                campo.setSelectionRange(campo.value.length, campo.value.length);
            }
        }

        function indiceDe(fila) {
            return [...articulo.querySelectorAll('[data-item]')].indexOf(fila);
        }

        function reconstruirLista(items, enfocar = null) {
            estado.items = items;
            construirCuerpo();
            guardar({ items });

            if (enfocar !== null) {
                enfocarItem(enfocar);
            }
        }

        /* ---------- Acciones ---------- */

        const acciones = {
            hecha() {
                guardar({ hecha: !estado.hecha });
                pintarHecha();
            },
            'agregar-item'() {
                reconstruirLista(insertarItemDespues(estado.items), estado.items.length);
            },
            'quitar-item'(elemento) {
                const indice = indiceDe(elemento.closest('[data-item]'));

                if (indice >= 0) {
                    reconstruirLista(quitarItem(estado.items, indice), indice - 1);
                }
            },
            menu() {
                articulo.dispatchEvent(new CustomEvent('caja:menu', { bubbles: true }));
            },
        };

        articulo.addEventListener('click', (evento) => {
            const tipo = evento.target.closest('[data-tipo]');

            if (tipo && articulo.contains(tipo)) {
                api.cambiarTipo(tipo.dataset.tipo);

                return;
            }

            const boton = evento.target.closest('[data-accion]');

            if (boton && articulo.contains(boton) && acciones[boton.dataset.accion]) {
                acciones[boton.dataset.accion](boton);
            }
        });

        articulo.addEventListener('input', (evento) => {
            const campo = evento.target;

            if (campo.matches('[data-campo="titulo"]')) {
                guardar({ titulo: campo.value });
            } else if (campo.matches('[data-campo="contenido"]')) {
                guardar({ contenido: campo.value });
            } else if (campo.matches('[data-item-texto]')) {
                const indice = indiceDe(campo.closest('[data-item]'));

                estado.items = cambiarTextoItem(estado.items, indice, campo.value);
                guardar({ items: estado.items });
            }
        });

        articulo.addEventListener('change', (evento) => {
            if (evento.target.matches('[data-item-check]')) {
                const fila = evento.target.closest('[data-item]');
                const indice = indiceDe(fila);

                estado.items = alternarItem(estado.items, indice);
                fila.classList.toggle('es-tildado', estado.items[indice].hecho);
                guardar({ items: estado.items });
            }
        });

        articulo.addEventListener('keydown', (evento) => {
            const campo = evento.target;

            if (!campo.matches('[data-item-texto]')) {
                return;
            }

            const fila = campo.closest('[data-item]');
            const indice = indiceDe(fila);

            if (evento.key === 'Enter' && !evento.shiftKey && !evento.isComposing) {
                evento.preventDefault();
                reconstruirLista(insertarItemDespues(estado.items, indice), indice + 1);
            } else if (evento.key === 'Backspace' && campo.value === '' && estado.items.length > 1) {
                evento.preventDefault();
                reconstruirLista(quitarItem(estado.items, indice), indice - 1);
            } else if (evento.key === 'ArrowUp' && indice > 0) {
                evento.preventDefault();
                enfocarItem(indice - 1);
            } else if (evento.key === 'ArrowDown' && indice < estado.items.length - 1) {
                evento.preventDefault();
                enfocarItem(indice + 1);
            }
        });

        const api = {
            articulo,
            clave,
            estado,
            guardar,

            /** Aplica un cambio hecho desde el menú de opciones. */
            aplicar(parche) {
                guardar(parche);

                if ('contexto_id' in parche) pintarActividad();
                if ('hora_inicio' in parche || 'hora_fin' in parche) pintarHora();
                if ('hecha' in parche) pintarHecha();
                if ('borde_grosor' in parche || 'borde_color' in parche) pintarBorde();
            },

            cambiarTipo(tipo) {
                if (tipo === estado.tipo || !['texto', 'lista'].includes(tipo)) {
                    return;
                }

                const parche = { tipo };

                if (tipo === 'lista') {
                    // El texto que ya estaba escrito pasa a ser la lista (un ítem por renglón).
                    const items = estado.items.length > 0 ? estado.items : textoALista(estado.contenido);

                    parche.items = items.length > 0 ? items : [{ texto: '', hecho: false }];
                } else if (!(estado.contenido ?? '').trim()) {
                    parche.contenido = listaATexto(estado.items);
                }

                Object.assign(estado, parche);
                construirCuerpo();
                pintarSelectorDeTipo();
                guardar(parche);
            },

            enfocarTitulo() {
                articulo.querySelector('[data-campo="titulo"]')?.focus();
            },
        };

        pintarActividad();
        pintarHora();
        pintarHecha();
        pintarSelectorDeTipo();
        controladores.set(articulo, api);

        return api;
    }

    return {
        iniciar,
        iniciarTodas(raiz = document) {
            raiz.querySelectorAll('[data-caja]').forEach(iniciar);
        },
        obtener: (articulo) => controladores.get(articulo) ?? null,
    };
}
