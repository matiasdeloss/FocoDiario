/*
 * Tarjeta Pomodoro de la pantalla Hoy. No tiene motor propio: se suscribe al mismo motor único que usan
 * Estudio y el mini-temporizador (pomodoro-motor.js), dibuja lo que publica y le pide acciones.
 * Los tiempos salen de la última configuración guardada (localStorage). Si hay una sesión en curso al
 * abrir Hoy, la tarjeta la refleja porque el motor la recupera del estado guardado.
 */
import {
    acciones, conciliarSesion, configGuardada, crearSesionEnServidor, estadoGuardado, guardarDuracion, iniciarEstado, iniciarMotor,
    prepararAudio, suscribir, terminarSesion,
} from './pomodoro-motor.js';
import { hacerEditable } from './reloj-editable.js';
import { describir, estaPausado, formatearTiempo, LIBRE } from './pomodoro-logica.js';
import {
    actualizarContador, botonPrincipal, CIRCUNFERENCIA, contadorInicial, desplazamientoAnillo, etiquetaFase, segundosDeModo,
    modoDeEstado, PUNTOS, puntosLlenos, tamanoReloj,
} from './hoy-pomodoro-logica.js';

function iniciarTarjeta(raiz) {
    const q = (selector) => raiz.querySelector(selector);
    const elementos = {
        botonesModo: [...raiz.querySelectorAll('[data-modo]')],
        reloj: q('[data-reloj]'),
        fase: q('[data-fase-texto]'),
        anillo: q('[data-anillo]'),
        principal: q('[data-accion="principal"]'),
        reiniciar: q('[data-accion="reiniciar"]'),
        extra: q('[data-extra]'),
        mensaje: q('[data-mensaje]'),
        anuncio: q('[data-anuncio]'),
        puntos: q('[data-puntos]'),
    };
    const baseServidor = Number(raiz.dataset.completadosHoy) || 0;
    const sesionActivaServidor = raiz.dataset.sesionActiva ? Number(raiz.dataset.sesionActiva) : null;
    let modoElegido = 'foco';
    let contador;
    let ultimaFraseAnunciada = '';
    let iniciando = false;
    let modoActual = 'foco';
    const NOMBRES_MODO = { foco: 'Enfoque', descanso: 'Descanso', largo: 'Pausa larga' };

    const relojEditable = hacerEditable(elementos.reloj, {
        nombre: () => NOMBRES_MODO[modoActual],
        segundos: () => segundosDeModo(configGuardada(), modoActual),
        puedeEditar: () => !estadoGuardado(),
        confirmar: (seg) => {
            guardarDuracion(modoActual, seg);
            dibujar({ estado: null, ahora: Date.now() });
        },
        error: (texto) => { elementos.mensaje.textContent = texto; },
        anunciar: (texto) => { elementos.anuncio.textContent = texto; ultimaFraseAnunciada = texto; },
    });

    elementos.anillo.style.strokeDasharray = CIRCUNFERENCIA.toFixed(2);

    function dibujarPuntos() {
        const total = baseServidor + contador.extra;
        const llenos = puntosLlenos(total, PUNTOS);

        [...elementos.puntos.children].forEach((punto, i) => punto.classList.toggle('es-lleno', i < llenos));
        elementos.puntos.setAttribute('aria-label', `${total} ${total === 1 ? 'pomodoro completado' : 'pomodoros completados'} hoy`);
        elementos.puntos.title = `${total} hoy`;
    }

    function anunciar(texto) {
        if (texto !== ultimaFraseAnunciada) {
            elementos.anuncio.textContent = texto;
            ultimaFraseAnunciada = texto;
        }
    }

    function dibujar({ estado, ahora }) {
        const activo = estado !== null;
        const pausado = activo && estaPausado(estado);
        const config = configGuardada();

        contador = actualizarContador(contador, estado);
        dibujarPuntos();

        const modo = activo ? modoDeEstado(estado) : modoElegido;

        modoActual = modo;
        const d = activo ? describir(estado, ahora) : null;

        raiz.dataset.fase = activo ? estado.fase : 'inactivo';
        raiz.dataset.pausado = pausado ? 'true' : 'false';

        // El control segmentado elige con qué arrancar; con una sesión en curso solo muestra la fase actual.
        elementos.botonesModo.forEach((boton) => {
            boton.setAttribute('aria-pressed', boton.dataset.modo === modo ? 'true' : 'false');
            boton.disabled = activo;
        });

        const textoReloj = activo ? d.texto : formatearTiempo(segundosDeModo(config, modo) * 1000);

        elementos.reloj.textContent = textoReloj;
        elementos.reloj.dataset.tamano = tamanoReloj(textoReloj);
        elementos.fase.textContent = etiquetaFase(estado, pausado, modo);
        relojEditable.actualizar();
        elementos.anillo.style.setProperty('--hoy-anillo', desplazamientoAnillo(activo ? d.progreso : 0).toFixed(2));

        const principal = botonPrincipal(estado, pausado);

        elementos.principal.textContent = principal.texto;
        elementos.principal.dataset.accionActual = principal.accion;
        elementos.principal.disabled = iniciando;
        elementos.reiniciar.disabled = !activo || estado.fase === LIBRE;
        elementos.extra.hidden = !activo;

        if (activo) {
            anunciar(`${d.nombre}${pausado ? ', en pausa' : ''}`);
        } else {
            ultimaFraseAnunciada = '';
        }
    }

    async function iniciar() {
        if (iniciando) return;

        iniciando = true;
        elementos.principal.disabled = true;
        elementos.mensaje.textContent = '';
        prepararAudio();

        try {
            const config = configGuardada();
            const resultado = await crearSesionEnServidor(config);

            if (!resultado.ok) {
                elementos.mensaje.textContent = resultado.errores.join(' ');

                return;
            }

            iniciarEstado(resultado.id, { foco: config.foco, descanso: config.descanso, largo: config.largo, ciclos: config.ciclos }, modoElegido);
        } catch {
            elementos.mensaje.textContent = 'No se pudo conectar con el servidor. Revisá tu conexión y probá de nuevo.';
        } finally {
            iniciando = false;
            elementos.principal.disabled = false;
        }
    }

    // Elegir modo (solo sin sesión en curso).
    elementos.botonesModo.forEach((boton) => {
        boton.addEventListener('click', () => {
            if (estadoGuardado()) return;

            modoElegido = boton.dataset.modo;
            dibujar({ estado: null, ahora: Date.now() });
        });
    });

    elementos.principal.addEventListener('click', () => {
        elementos.mensaje.textContent = '';

        switch (elementos.principal.dataset.accionActual) {
            case 'pausar': acciones.pausar(); break;
            case 'reanudar': acciones.reanudar(); break;
            case 'siguiente-foco': acciones.siguienteFoco(); break;
            default: iniciar();
        }
    });

    elementos.reiniciar.addEventListener('click', () => acciones.reiniciar());
    q('[data-accion="saltar"]').addEventListener('click', () => acciones.saltar());
    q('[data-accion="terminar"]').addEventListener('click', () => terminarSesion());

    iniciarMotor();
    conciliarSesion(sesionActivaServidor);
    contador = contadorInicial(estadoGuardado());
    suscribir(dibujar);
}

document.addEventListener('DOMContentLoaded', () => {
    const raiz = document.getElementById('hoy-pomodoro');

    if (raiz) iniciarTarjeta(raiz);
});
