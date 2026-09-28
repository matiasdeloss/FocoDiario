/*
 * Tarjeta del temporizador en la vista Estudio (formulario, tiempo grande y botones).
 *
 * El motor (fases, guardado, envío al servidor, avisos, sincronía entre pestañas) vive en
 * pomodoro-motor.js y lo comparte con el mini-temporizador de la barra superior; esta tarjeta
 * solo dibuja lo que el motor publica y le pide acciones.
 * Configuración (tiempos, estilo, tarea, contexto, tema): localStorage, solo para precargar el formulario.
 */
import {
    acciones as accionesMotor, CLAVE_CONFIG, CLAVE_SONIDO, descartar, encabezados, escribir, estadoGuardado,
    finalizarEnServidor, iniciarEstado, iniciarMotor, leer, prepararAudio, sonidoActivo, suscribir, terminarSesion,
} from './pomodoro-motor.js';
import { DESCANSO, describir, estaPausado, FOCO, formatearTiempo, LIBRE, transcurridoMs } from './pomodoro-logica.js';

const CONFIG_INICIAL = { foco: 25, descanso: 5, largo: 15, ciclos: 4, estilo: 'clasico', tarea_id: '', contexto_id: '', tema: '' };

const config = () => ({ ...CONFIG_INICIAL, ...leer(CLAVE_CONFIG, {}) });

/* ---------- Tarjeta de Hoy: solo muestra el último ajuste ---------- */
function resumenEnHoy() {
    const destino = document.querySelector('[data-pomodoro-resumen]');

    if (!destino) return;

    const c = config();
    destino.textContent = `${String(c.foco).padStart(2, '0')}:00`;
    const detalle = document.querySelector('[data-pomodoro-resumen-detalle]');

    if (detalle) detalle.textContent = `Foco ${c.foco} min, descanso ${c.descanso} min`;
}

/* ---------- Temporizador en la vista Estudio ---------- */
function iniciarTemporizador(raiz) {
    const q = (selector) => raiz.querySelector(selector);
    const campos = {
        preset: q('#p-preset'), foco: q('#p-foco'), descanso: q('#p-descanso'), largo: q('#p-largo'),
        ciclos: q('#p-ciclos'), tarea: q('#p-tarea'), contexto: q('#p-contexto'), tema: q('#p-tema'),
    };
    const presets = JSON.parse(raiz.dataset.presets);
    const urlSesiones = raiz.dataset.urlSesiones;
    let estado = null;

    /* --- Formulario --- */
    function mostrarErrores(mensajes) {
        const caja = q('[data-p="errores"]');

        caja.hidden = mensajes.length === 0;
        caja.replaceChildren(...mensajes.map((texto) => Object.assign(document.createElement('div'), { textContent: texto })));
    }

    function aplicarConfig(c) {
        campos.foco.value = c.foco;
        campos.descanso.value = c.descanso;
        campos.largo.value = c.largo;
        campos.ciclos.value = c.ciclos;
        campos.preset.value = c.estilo;
        campos.tarea.value = c.tarea_id ?? '';
        campos.contexto.value = c.contexto_id ?? '';
        campos.tema.value = c.tema ?? '';
    }

    function leerFormulario() {
        return {
            foco: Number(campos.foco.value), descanso: Number(campos.descanso.value),
            largo: Number(campos.largo.value), ciclos: Number(campos.ciclos.value),
            estilo: campos.preset.value, tarea_id: campos.tarea.value, contexto_id: campos.contexto.value,
            tema: campos.tema.value.trim(),
        };
    }

    function detectarEstilo() {
        const c = leerFormulario();
        const coincide = Object.entries(presets).find(([, p]) => p.foco === c.foco && p.descanso === c.descanso && p.largo === c.largo && p.ciclos === c.ciclos);

        campos.preset.value = coincide ? coincide[0] : 'personalizado';
        escribir(CLAVE_CONFIG, leerFormulario());
    }

    campos.preset.addEventListener('change', () => {
        const preset = presets[campos.preset.value];

        if (preset) {
            campos.foco.value = preset.foco;
            campos.descanso.value = preset.descanso;
            campos.largo.value = preset.largo;
            campos.ciclos.value = preset.ciclos;
        }

        escribir(CLAVE_CONFIG, leerFormulario());
    });
    [campos.foco, campos.descanso, campos.largo, campos.ciclos].forEach((c) => c.addEventListener('input', detectarEstilo));
    [campos.tarea, campos.contexto, campos.tema].forEach((c) => c.addEventListener('change', () => escribir(CLAVE_CONFIG, leerFormulario())));

    /* --- Dibujo --- */
    function dibujar(foto) {
        estado = foto.estado;

        const ahora = foto.ahora;
        const activo = estado !== null;
        const c = activo ? estado.config : leerFormulario();

        raiz.dataset.fase = activo ? estado.fase : 'inactivo';
        raiz.dataset.pausado = activo && estaPausado(estado) ? 'true' : 'false';
        q('#p-formulario').disabled = activo;
        q('[data-p="resumen-sesion"]').hidden = !activo;

        let texto = formatearTiempo(c.foco * 60_000);
        let etiqueta = 'Listo para empezar';
        let ayuda = 'Elegí los tiempos y qué vas a trabajar. El tiempo sigue corriendo aunque cambies de pestaña o de sección.';

        if (activo) {
            const d = describir(estado, ahora);

            texto = d.texto;
            etiqueta = d.nombre;

            if (estado.fase === LIBRE) {
                ayuda = 'El descanso terminó. Este tiempo se registra hasta que empieces el siguiente foco.';
            } else {
                ayuda = d.pausado
                    ? 'En pausa. El tiempo en pausa no cuenta como foco ni como descanso.'
                    : (estado.fase === FOCO ? 'Una sola tarea, sin distracciones.' : 'Pararte, tomar agua y mirar lejos. Sin celular.');
            }

            const enFoco = estado.fase === FOCO;
            const numero = Math.min(estado.enCiclo + (enFoco ? 1 : 0), estado.config.ciclos);

            q('[data-p="ciclo"]').textContent = `Pomodoro ${Math.max(numero, 1)} de ${estado.config.ciclos}`;
            q('[data-p="puntos"]').replaceChildren(...Array.from({ length: estado.config.ciclos }, (_, i) => {
                const punto = document.createElement('span');

                punto.className = `pomodoro-punto${i < estado.enCiclo ? ' lleno' : ''}${enFoco && i === estado.enCiclo ? ' actual' : ''}`;

                return punto;
            }));
            q('[data-p="completados"]').textContent = estado.completados;
            q('[data-p="interrumpidos"]').textContent = estado.interrumpidos;
            q('[data-p="descanso"]').textContent = minutos(estado.totalDescansoSeg + (estado.fase === DESCANSO ? transcurridoMs(estado, ahora) / 1000 : 0));
            q('[data-p="libre"]').textContent = minutos(estado.totalLibreSeg + (estado.fase === LIBRE ? transcurridoMs(estado, ahora) / 1000 : 0));
            q('[data-p="detalle"]').textContent = detalleSesion();
        } else {
            q('[data-p="ciclo"]').textContent = '';
            q('[data-p="puntos"]').replaceChildren();
        }

        q('[data-p="tiempo"]').textContent = texto;
        q('[data-p="fase"]').textContent = etiqueta;
        q('[data-p="ayuda"]').textContent = ayuda;

        raiz.querySelectorAll('[data-p-accion]').forEach((boton) => {
            const visibles = boton.dataset.visible.split(' ');
            const clave = !activo ? 'inactivo' : (estaPausado(estado) ? `${estado.fase}-pausa` : estado.fase);

            boton.hidden = !visibles.includes(clave);
        });
    }

    const minutos = (seg) => `${Math.round(seg / 60)} min`;

    function detalleSesion() {
        const partes = [];

        if (campos.tema.value) partes.push(campos.tema.value);
        if (campos.tarea.selectedOptions[0]?.value) partes.push(campos.tarea.selectedOptions[0].textContent.trim());
        if (campos.contexto.selectedOptions[0]?.value) partes.push(campos.contexto.selectedOptions[0].textContent.trim());

        return partes.join(' · ');
    }

    /* --- Acciones --- */
    async function iniciar() {
        mostrarErrores([]);

        const formulario = q('#p-formulario');
        const invalido = [...formulario.querySelectorAll('input[type="number"]')].find((campo) => !campo.checkValidity());

        if (invalido) {
            mostrarErrores([`${invalido.dataset.nombre}: ${invalido.validationMessage}`]);
            invalido.focus();

            return;
        }

        const c = leerFormulario();
        const boton = q('[data-p-accion="iniciar"]');

        boton.disabled = true;
        prepararAudio();

        try {
            const respuesta = await fetch(urlSesiones, {
                method: 'POST',
                headers: encabezados(),
                body: JSON.stringify({
                    tarea_id: c.tarea_id || null, contexto_id: c.contexto_id || null, tema: c.tema || null,
                    estilo: c.estilo, foco_min: c.foco, descanso_min: c.descanso,
                    descanso_largo_min: c.largo, pomodoros_antes_largo: c.ciclos,
                }),
            });
            const datos = await respuesta.json().catch(() => ({}));

            if (!respuesta.ok) {
                mostrarErrores(datos.errors ? Object.values(datos.errors).flat() : ['No se pudo iniciar la sesión. Probá de nuevo.']);

                return;
            }

            escribir(CLAVE_CONFIG, c);
            iniciarEstado(datos.id, { foco: c.foco, descanso: c.descanso, largo: c.largo, ciclos: c.ciclos });
        } catch {
            mostrarErrores(['No se pudo conectar con el servidor. Revisá tu conexión y probá de nuevo.']);
        } finally {
            boton.disabled = false;
        }
    }

    const acciones = {
        iniciar,
        pausar: accionesMotor.pausar,
        reanudar: accionesMotor.reanudar,
        reiniciar: accionesMotor.reiniciar,
        saltar: accionesMotor.saltar,
        'siguiente-foco': accionesMotor.siguienteFoco,
        terminar: async () => {
            if (await terminarSesion()) {
                mostrarErrores([]);
                q('[data-p="terminada"]').hidden = false;
            }
        },
    };

    raiz.querySelectorAll('[data-p-accion]').forEach((boton) => {
        boton.addEventListener('click', () => {
            q('[data-p="terminada"]').hidden = true;
            acciones[boton.dataset.pAccion]?.();
        });
    });

    // Sonido y notificaciones
    const casillaSonido = q('#p-sonido');
    const botonNotificar = q('[data-p="notificar"]');

    casillaSonido.checked = sonidoActivo();
    casillaSonido.addEventListener('change', () => escribir(CLAVE_SONIDO, casillaSonido.checked));

    function estadoNotificaciones() {
        if (!('Notification' in window)) {
            botonNotificar.hidden = true;

            return;
        }

        botonNotificar.hidden = Notification.permission !== 'default';
    }

    botonNotificar.addEventListener('click', async () => {
        await Notification.requestPermission();
        estadoNotificaciones();
    });
    estadoNotificaciones();

    /* --- Arranque --- */
    const sesionActivaServidor = raiz.dataset.sesionActiva ? Number(raiz.dataset.sesionActiva) : null;

    aplicarConfig(config());
    iniciarMotor();

    const guardado = estadoGuardado();

    if (guardado && guardado.sesionId !== sesionActivaServidor) {
        // La sesión guardada ya no está en curso en el servidor (se borró o se cerró): se descarta.
        descartar();
    } else if (!guardado && sesionActivaServidor) {
        // Quedó una sesión abierta sin estado local (otro navegador o datos borrados): se cierra.
        finalizarEnServidor(sesionActivaServidor);
    }

    if (!estadoGuardado()) {
        const preset = new URLSearchParams(window.location.search).get('preset');

        if (preset && presets[preset]) {
            campos.preset.value = preset;
            campos.preset.dispatchEvent(new Event('change'));
        }
    }

    suscribir(dibujar);
}

document.addEventListener('DOMContentLoaded', () => {
    resumenEnHoy();

    const raiz = document.getElementById('pomodoro');

    if (raiz) iniciarTemporizador(raiz);
});
