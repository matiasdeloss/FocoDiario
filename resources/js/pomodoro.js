/*
 * Tarjeta del temporizador en la vista Estudio (formulario, tiempo grande y botones).
 *
 * El motor (fases, guardado, envío al servidor, avisos, sincronía entre pestañas) vive en
 * pomodoro-motor.js y lo comparte con el mini-temporizador de la barra superior; esta tarjeta
 * solo dibuja lo que el motor publica y le pide acciones.
 * Configuración (tiempos, estilo, tarea, contexto, tema): localStorage, solo para precargar el formulario.
 */
import {
    acciones as accionesMotor, CLAVE_CONFIG, CLAVE_SONIDO, conciliarSesion, configGuardada as config, crearSesionEnServidor,
    escribir, estadoGuardado, iniciarEstado, iniciarMotor, prepararAudio, sonidoActivo, suscribir, terminarSesion,
} from './pomodoro-motor.js';
import { hacerEditable } from './reloj-editable.js';
import {
    DESCANSO, describir, dividirSegundos, estaPausado, FOCO, formatearDuracion, formatearTiempo, LIBRE, transcurridoMs, validarDuracion,
} from './pomodoro-logica.js';
import { tamanoReloj } from './hoy-pomodoro-logica.js';

/* ---------- Temporizador en la vista Estudio ---------- */
function iniciarTemporizador(raiz) {
    const q = (selector) => raiz.querySelector(selector);
    const campos = {
        preset: q('#p-preset'), ciclos: q('#p-ciclos'), tarea: q('#p-tarea'), contexto: q('#p-contexto'), tema: q('#p-tema'),
    };
    // Cada duración se escribe en dos campos (min y seg) y se guarda en segundos.
    const duraciones = Object.fromEntries(['foco', 'descanso', 'largo'].map((clave) => [clave, {
        grupo: q(`[data-duracion="${clave}"]`), min: q(`#p-${clave}-min`), seg: q(`#p-${clave}-seg`),
    }]));
    const camposTiempo = Object.values(duraciones).flatMap((d) => [d.min, d.seg]);
    const presets = JSON.parse(raiz.dataset.presets);
    let estado = null;

    /* --- Formulario --- */
    function mostrarErrores(mensajes) {
        const caja = q('[data-p="errores"]');

        caja.hidden = mensajes.length === 0;
        caja.replaceChildren(...mensajes.map((texto) => Object.assign(document.createElement('div'), { textContent: texto })));
    }

    function aplicarConfig(c) {
        ponerDuraciones(c);
        campos.ciclos.value = c.ciclos;
        campos.preset.value = c.estilo;
        campos.tarea.value = c.tarea_id ?? '';
        campos.contexto.value = c.contexto_id ?? '';
        campos.tema.value = c.tema ?? '';
    }

    function ponerDuraciones(c) {
        Object.entries(duraciones).forEach(([clave, d]) => {
            const { min, seg } = dividirSegundos(c[clave]);

            d.min.value = min;
            d.seg.value = seg;
        });
    }

    /** Segundos de una duración según sus dos campos; NaN si no es válida (la validación al iniciar explica por qué). */
    const segundosDe = (d) => {
        const r = validarDuracion(d.min.value, d.seg.value);

        return r.ok ? r.seg : Number.NaN;
    };

    function leerFormulario() {
        return {
            foco: segundosDe(duraciones.foco), descanso: segundosDe(duraciones.descanso),
            largo: segundosDe(duraciones.largo), ciclos: Number(campos.ciclos.value),
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
            ponerDuraciones(preset);
            campos.ciclos.value = preset.ciclos;
        }

        escribir(CLAVE_CONFIG, leerFormulario());
    });
    [...camposTiempo, campos.ciclos].forEach((c) => c.addEventListener('input', detectarEstilo));
    [campos.tarea, campos.contexto, campos.tema].forEach((c) => c.addEventListener('change', () => escribir(CLAVE_CONFIG, leerFormulario())));

    /* --- Reloj editable: sin fase en marcha, tocarlo cambia la duración del foco --- */
    const relojEditable = hacerEditable(q('[data-p="tiempo"]'), {
        nombre: () => 'foco',
        segundos: () => (Number.isFinite(leerFormulario().foco) ? leerFormulario().foco : config().foco),
        puedeEditar: () => estado === null,
        confirmar: (seg) => {
            ponerDuraciones({ ...leerFormularioSeguro(), foco: seg });
            detectarEstilo();
            q('[data-p="tiempo"]').textContent = formatearTiempo(seg * 1000);
            relojEditable.actualizar();
        },
        error: (texto) => mostrarErrores(texto ? [texto] : []),
        anunciar: (texto) => { q('[data-p="anuncio"]').textContent = texto; },
    });

    /** Formulario con los valores inválidos reemplazados por los guardados, para no escribir NaN en los campos. */
    function leerFormularioSeguro() {
        const c = leerFormulario();
        const guardada = config();

        return { foco: Number.isFinite(c.foco) ? c.foco : guardada.foco, descanso: Number.isFinite(c.descanso) ? c.descanso : guardada.descanso, largo: Number.isFinite(c.largo) ? c.largo : guardada.largo };
    }

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

        let texto = formatearTiempo((Number.isFinite(c.foco) ? c.foco : 0) * 1000);
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
        q('[data-p="tiempo"]').dataset.tamano = tamanoReloj(texto);
        relojEditable.actualizar();
        q('[data-p="fase"]').textContent = etiqueta;
        q('[data-p="ayuda"]').textContent = ayuda;

        raiz.querySelectorAll('[data-p-accion]').forEach((boton) => {
            const visibles = boton.dataset.visible.split(' ');
            const clave = !activo ? 'inactivo' : (estaPausado(estado) ? `${estado.fase}-pausa` : estado.fase);

            boton.hidden = !visibles.includes(clave);
        });
    }

    const minutos = (seg) => formatearDuracion(seg);

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

        const errores = [];

        Object.values(duraciones).forEach((d) => {
            const r = validarDuracion(d.min.value, d.seg.value, d.grupo.dataset.nombre);

            [d.min, d.seg].forEach((campo) => campo.setAttribute('aria-invalid', r.ok ? 'false' : 'true'));

            if (!r.ok) errores.push(r.error);
        });

        if (!campos.ciclos.checkValidity()) {
            errores.push(`Pomodoros por ciclo: ${campos.ciclos.validationMessage}`);
            campos.ciclos.setAttribute('aria-invalid', 'true');
        } else {
            campos.ciclos.setAttribute('aria-invalid', 'false');
        }

        if (errores.length > 0) {
            mostrarErrores(errores);
            (camposTiempo.find((campo) => campo.getAttribute('aria-invalid') === 'true') ?? campos.ciclos).focus();

            return;
        }

        const c = leerFormulario();
        const boton = q('[data-p-accion="iniciar"]');

        boton.disabled = true;
        prepararAudio();

        try {
            const resultado = await crearSesionEnServidor(c);

            if (!resultado.ok) {
                mostrarErrores(resultado.errores);

                return;
            }

            escribir(CLAVE_CONFIG, c);
            iniciarEstado(resultado.id, { foco: c.foco, descanso: c.descanso, largo: c.largo, ciclos: c.ciclos });
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

    // Un tiempo escrito en el reloj de Hoy se guarda como personalizado: si coincide con un estilo, se lo reconoce.
    if (config().estilo === 'personalizado') detectarEstilo();
    iniciarMotor();

    conciliarSesion(sesionActivaServidor);

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
    const raiz = document.getElementById('pomodoro');

    if (raiz) iniciarTemporizador(raiz);
});
