/*
 * Mini-temporizador de la barra superior. No tiene lógica propia: se suscribe al motor único
 * (pomodoro-motor.js), dibuja lo que publica y le pide acciones. Por eso se reconstruye solo,
 * en cualquier página, desde el estado guardado.
 */
import { acciones, iniciarMotor, suscribir, terminarSesion } from './pomodoro-motor.js';
import { describir, LIBRE } from './pomodoro-logica.js';

function iniciarWidget(raiz) {
    const q = (selector) => raiz.querySelector(selector);
    const elementos = {
        fase: q('[data-w="fase"]'),
        tiempo: q('[data-w="tiempo"]'),
        barra: q('[data-w="barra"]'),
        pausa: q('[data-w-accion="pausa"]'),
        pausaIcono: q('[data-w-accion="pausa"] i'),
        saltar: q('[data-w-accion="saltar"]'),
        anuncio: q('[data-w="anuncio"]'),
    };
    let ultimaFase = null;
    let ultimoPausado = null;

    function dibujar({ estado, ahora }) {
        if (!estado) {
            raiz.hidden = true;
            ultimaFase = null;
            ultimoPausado = null;

            return;
        }

        const d = describir(estado, ahora);
        const libre = estado.fase === LIBRE;

        raiz.hidden = false;
        raiz.dataset.fase = estado.fase;
        raiz.dataset.pausado = d.pausado ? 'true' : 'false';

        elementos.fase.textContent = d.pausado ? `${d.corta} · pausa` : d.corta;
        elementos.tiempo.textContent = d.texto;
        elementos.barra.style.setProperty('--progreso', d.progreso ?? 0);

        // Pausar y reanudar son el mismo botón (conserva el foco del teclado): cambian ícono y etiqueta.
        elementos.pausa.hidden = libre;
        elementos.pausa.setAttribute('aria-label', d.pausado ? 'Reanudar temporizador' : 'Pausar temporizador');
        elementos.pausa.title = d.pausado ? 'Reanudar' : 'Pausar';
        elementos.pausaIcono.className = `bi ${d.pausado ? 'bi-play-fill' : 'bi-pause-fill'}`;
        elementos.saltar.setAttribute('aria-label', libre ? 'Iniciar el siguiente foco' : 'Saltar a la siguiente fase');
        elementos.saltar.title = libre ? 'Siguiente foco' : 'Saltar fase';

        // Aviso a lectores de pantalla solo cuando cambia la fase o el estado de pausa, no cada segundo.
        if (estado.fase !== ultimaFase || d.pausado !== ultimoPausado) {
            if (ultimaFase !== null || ultimoPausado !== null) {
                elementos.anuncio.textContent = d.pausado ? `${d.nombre}, en pausa` : d.nombre;
            }

            ultimaFase = estado.fase;
            ultimoPausado = d.pausado;
        }
    }

    q('[data-w-accion="pausa"]').addEventListener('click', () => {
        if (raiz.dataset.pausado === 'true') acciones.reanudar();
        else acciones.pausar();
    });
    q('[data-w-accion="saltar"]').addEventListener('click', () => {
        if (raiz.dataset.fase === LIBRE) acciones.siguienteFoco();
        else acciones.saltar();
    });
    q('[data-w-accion="terminar"]').addEventListener('click', () => terminarSesion());

    iniciarMotor();
    suscribir(dibujar);
}

document.addEventListener('DOMContentLoaded', () => {
    const raiz = document.getElementById('pomodoro-widget');

    if (raiz) iniciarWidget(raiz);
});
