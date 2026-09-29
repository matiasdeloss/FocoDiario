// Verificación de la lógica de fases del temporizador. Se ejecuta con: node tests/js/pomodoro-logica.test.mjs
import assert from 'node:assert/strict';
import {
    avanzar, CONFIG_POR_DEFECTO, crearEstado, desplazamientoAnillo, DESCANSO, fraccionAnillo, dividirSegundos, FOCO, formatearDuracion, formatearTiempo, formatearTranscurrido,
    iniciarSiguienteFoco, interpretarTiempo, LIBRE, migrarConfig, migrarEstado, migrarEvento, pausar, reanudar, reiniciar, restanteMs, saltar, terminar,
    transcurridoMs, validarDuracion,
} from '../../resources/js/pomodoro-logica.js';

const MIN = 60_000;
const config = { foco: 1500, descanso: 300, largo: 900, ciclos: 4 };
const t0 = Date.UTC(2026, 8, 29, 12, 0, 0);
let pruebas = 0;
const prueba = (nombre, fn) => { fn(); pruebas++; console.log('ok -', nombre); };

prueba('el tiempo restante se calcula contra marcas, no contando ticks', () => {
    const e = crearEstado(1, config, t0);
    assert.equal(restanteMs(e, t0), 25 * MIN);
    // Pestaña dormida 10 minutos: un único cálculo da el tiempo correcto.
    assert.equal(restanteMs(e, t0 + 10 * MIN), 15 * MIN);
    assert.equal(formatearTiempo(restanteMs(e, t0 + 10 * MIN)), '15:00');
});

prueba('pausar congela el tiempo y reanudar lo continúa', () => {
    let e = crearEstado(1, config, t0);
    e = pausar(e, t0 + 5 * MIN);
    assert.equal(restanteMs(e, t0 + 60 * MIN), 20 * MIN);
    e = reanudar(e, t0 + 30 * MIN);
    assert.equal(restanteMs(e, t0 + 30 * MIN), 20 * MIN);
    assert.equal(transcurridoMs(e, t0 + 35 * MIN), 10 * MIN);
    // Una fase en pausa no avanza aunque pase mucho tiempo.
    const enPausa = pausar(e, t0 + 31 * MIN);
    assert.equal(avanzar(enPausa, t0 + 500 * MIN).eventos.length, 0);
});

prueba('foco completo pasa a descanso y registra el intervalo con hora exacta', () => {
    const { estado, eventos } = avanzar(crearEstado(7, config, t0), t0 + 25 * MIN + 2000);
    assert.equal(estado.fase, DESCANSO);
    assert.equal(estado.completados, 1);
    assert.equal(eventos.length, 1);
    assert.deepEqual(
        { tipo: eventos[0].tipo, inicio: eventos[0].inicio, fin: eventos[0].fin, completado: eventos[0].completado, plan: eventos[0].planificado_seg },
        { tipo: FOCO, inicio: t0, fin: t0 + 25 * MIN, completado: true, plan: 1500 },
    );
    assert.equal(estado.faseInicio, t0 + 25 * MIN);
});

prueba('descanso agotado pasa a tiempo libre y el libre cuenta hacia arriba', () => {
    const { estado, eventos } = avanzar(crearEstado(1, config, t0), t0 + 40 * MIN);
    assert.equal(estado.fase, LIBRE);
    assert.deepEqual(eventos.map((e) => e.tipo), [FOCO, DESCANSO]);
    assert.equal(eventos[1].inicio, t0 + 25 * MIN);
    assert.equal(eventos[1].fin, t0 + 30 * MIN);
    assert.equal(transcurridoMs(estado, t0 + 40 * MIN), 10 * MIN);
});

prueba('iniciar el siguiente foco registra el tiempo libre real', () => {
    const { estado } = avanzar(crearEstado(1, config, t0), t0 + 34 * MIN);
    const r = iniciarSiguienteFoco(estado, t0 + 34 * MIN);
    assert.equal(r.eventos.length, 1);
    assert.equal(r.eventos[0].tipo, LIBRE);
    assert.equal(r.eventos[0].fin - r.eventos[0].inicio, 4 * MIN);
    assert.equal(r.estado.fase, FOCO);
    assert.equal(r.estado.totalLibreSeg, 240);
});

prueba('saltar el foco lo registra interrumpido con su duración real y no suma pomodoro', () => {
    const r = saltar(crearEstado(1, config, t0), t0 + 10 * MIN);
    assert.equal(r.eventos.length, 1);
    assert.equal(r.eventos[0].completado, false);
    assert.equal(r.eventos[0].fin - r.eventos[0].inicio, 10 * MIN);
    assert.equal(r.estado.completados, 0);
    assert.equal(r.estado.interrumpidos, 1);
    assert.equal(r.estado.fase, DESCANSO);
    assert.equal(r.estado.descansoLargo, false);
});

prueba('saltar el descanso registra un descanso más corto y arranca el foco', () => {
    const { estado } = avanzar(crearEstado(1, config, t0), t0 + 27 * MIN);
    const r = saltar(estado, t0 + 27 * MIN);
    assert.equal(r.eventos[0].tipo, DESCANSO);
    assert.equal(r.eventos[0].completado, false);
    assert.equal(r.eventos[0].fin - r.eventos[0].inicio, 2 * MIN);
    assert.equal(r.estado.fase, FOCO);
});

prueba('el descanso largo llega tras N pomodoros completos y los interrumpidos no cuentan', () => {
    const c = { foco: 1500, descanso: 300, largo: 900, ciclos: 2 };
    let e = crearEstado(1, c, t0);
    let ahora = t0;
    // Un foco interrumpido no cuenta para el ciclo.
    ahora += 5 * MIN;
    e = saltar(e, ahora).estado; // interrumpido -> descanso corto
    ahora += 2 * MIN;
    e = saltar(e, ahora).estado; // descanso saltado -> foco
    assert.equal(e.enCiclo, 0);
    // Primer foco completo: descanso corto.
    ahora += 25 * MIN;
    e = avanzar(e, ahora).estado;
    assert.equal(e.fase, DESCANSO);
    assert.equal(e.planificadoSeg, 5 * 60);
    ahora += 1 * MIN;
    e = saltar(e, ahora).estado; // foco
    // Segundo foco completo: descanso largo.
    ahora += 25 * MIN;
    e = avanzar(e, ahora).estado;
    assert.equal(e.fase, DESCANSO);
    assert.equal(e.planificadoSeg, 15 * 60);
    assert.equal(e.enCiclo, 0);
    assert.equal(e.completados, 2);
    assert.equal(e.interrumpidos, 1);
});

prueba('reabrir la pestaña mucho después reconstruye las fases con sus horas teóricas', () => {
    // Foco de 25, descanso de 5 y 2 horas de tiempo libre.
    const { estado, eventos } = avanzar(crearEstado(3, config, t0), t0 + 25 * MIN + 5 * MIN + 120 * MIN);
    assert.equal(estado.fase, LIBRE);
    assert.equal(eventos.length, 2);
    assert.equal(transcurridoMs(estado, t0 + 150 * MIN), 120 * MIN);
});

prueba('reiniciar vuelve la fase actual a cero sin registrar', () => {
    let e = crearEstado(1, config, t0);
    e = reiniciar(e, t0 + 10 * MIN);
    assert.equal(restanteMs(e, t0 + 10 * MIN), 25 * MIN);
});

prueba('terminar registra lo que estaba en curso; con cero segundos no registra nada', () => {
    const r = terminar(crearEstado(1, config, t0), t0 + 7 * MIN);
    assert.equal(r.estado, null);
    assert.equal(r.eventos.length, 1);
    assert.equal(r.eventos[0].completado, false);
    assert.equal(terminar(crearEstado(1, config, t0), t0).eventos.length, 0);
});

prueba('las pausas se descuentan de la duración y se informan', () => {
    let e = crearEstado(1, config, t0);
    e = pausar(e, t0 + 10 * MIN);
    e = reanudar(e, t0 + 15 * MIN);
    const r = saltar(e, t0 + 20 * MIN);
    assert.equal(r.eventos[0].pausado_seg, 300);
    assert.equal(r.eventos[0].fin - r.eventos[0].inicio, 20 * MIN);
    // Foco completado con pausa: dura 25 min efectivos, fin teórico 5 min más tarde.
    const c = avanzar(e, t0 + 31 * MIN);
    assert.equal(c.eventos[0].fin, t0 + 30 * MIN);
    assert.equal(c.eventos[0].pausado_seg, 300);
});

prueba('las claves de los intervalos no se repiten', () => {
    const { eventos } = avanzar(crearEstado(9, config, t0), t0 + 100 * MIN);
    assert.equal(new Set(eventos.map((e) => e.clave)).size, eventos.length);
});

/* ---------- Duraciones de segundos ---------- */
const SEG = 1000;
const corta = { foco: 5, descanso: 5, largo: 5, ciclos: 2 };

prueba('un foco de 5 segundos cuenta bien, pasa a descanso y registra el intervalo', () => {
    const e = crearEstado(1, corta, t0);
    assert.equal(e.planificadoSeg, 5);
    assert.equal(restanteMs(e, t0 + 2 * SEG), 3 * SEG);
    assert.equal(formatearTiempo(restanteMs(e, t0 + 2 * SEG)), '00:03');
    assert.equal(avanzar(e, t0 + 4 * SEG).eventos.length, 0);

    const { estado, eventos } = avanzar(e, t0 + 5 * SEG);
    assert.equal(estado.fase, DESCANSO);
    assert.equal(estado.completados, 1);
    assert.equal(eventos[0].planificado_seg, 5);
    assert.equal(eventos[0].fin - eventos[0].inicio, 5 * SEG);
});

prueba('con fases de 5 segundos se encadenan foco, descanso y tiempo libre, y el descanso largo llega a su ciclo', () => {
    const { estado, eventos } = avanzar(crearEstado(1, corta, t0), t0 + 12 * SEG);
    assert.equal(estado.fase, LIBRE);
    assert.deepEqual(eventos.map((ev) => [ev.tipo, ev.planificado_seg]), [[FOCO, 5], [DESCANSO, 5]]);
    assert.equal(transcurridoMs(estado, t0 + 12 * SEG), 2 * SEG);

    // Dos pomodoros por ciclo: el segundo foco completo lleva al descanso largo.
    const largo = avanzar(crearEstado(2, { ...corta, largo: 30 }, t0), t0 + 30 * SEG);
    const siguiente = iniciarSiguienteFoco(largo.estado, t0 + 30 * SEG);
    const dos = avanzar(siguiente.estado, t0 + 35 * SEG);
    assert.equal(dos.estado.descansoLargo, true);
    assert.equal(dos.estado.planificadoSeg, 30);
});

prueba('el formateo del tiempo cubre segundos, minutos y horas', () => {
    assert.equal(formatearTiempo(5 * SEG), '00:05');
    assert.equal(formatearTiempo(4_100), '00:05'); // la cuenta regresiva redondea hacia arriba
    assert.equal(formatearTiempo(180 * MIN), '3:00:00');
    assert.equal(formatearTiempo(59 * MIN + 59 * SEG), '59:59');
    assert.equal(formatearTiempo(2 * 3600 * SEG + 59 * MIN + 59 * SEG), '2:59:59');
    assert.equal(formatearTiempo(60 * MIN), '1:00:00');
    assert.equal(formatearTranscurrido(3_725 * SEG), '1:02:05');
    assert.equal(formatearTranscurrido(65 * SEG), '01:05');
});

prueba('formatearDuracion da el texto corto de una duración en segundos', () => {
    assert.equal(formatearDuracion(5), '5 s');
    assert.equal(formatearDuracion(90), '1 min 30 s');
    assert.equal(formatearDuracion(1500), '25 min');
    assert.equal(formatearDuracion(7500), '2 h 05 min');
    assert.equal(formatearDuracion(10800), '3 h');
    assert.equal(formatearDuracion(0), '0 s');
});

prueba('validarDuracion acepta de 5 s a 180 min y rechaza el resto con mensajes claros', () => {
    assert.deepEqual(validarDuracion(0, 5, 'Foco'), { ok: true, seg: 5 });
    assert.deepEqual(validarDuracion('180', '0', 'Foco'), { ok: true, seg: 10800 });
    assert.deepEqual(validarDuracion('', '30', 'Foco'), { ok: true, seg: 30 });
    assert.equal(validarDuracion(0, 4, 'Foco').ok, false);
    assert.match(validarDuracion(0, 4, 'Foco').error, /Foco: la duración debe estar entre 00:05 y 180:00/);
    assert.equal(validarDuracion(180, 1, 'Foco').ok, false);
    assert.equal(validarDuracion(0, 0, 'Foco').ok, false);
    assert.match(validarDuracion(1, 75, 'Foco').error, /los segundos van de 0 a 59/);
    assert.match(validarDuracion(1.5, 0, 'Foco').error, /números enteros/);
    assert.match(validarDuracion(-1, 30, 'Foco').error, /números enteros/);
    assert.match(validarDuracion('abc', 0, 'Foco').error, /números enteros/);
});

prueba('dividirSegundos parte una duración en minutos y segundos', () => {
    assert.deepEqual(dividirSegundos(5), { min: 0, seg: 5 });
    assert.deepEqual(dividirSegundos(1500), { min: 25, seg: 0 });
    assert.deepEqual(dividirSegundos(10800), { min: 180, seg: 0 });
    assert.deepEqual(dividirSegundos(95), { min: 1, seg: 35 });
});

prueba('la configuración vieja en minutos se convierte a segundos', () => {
    const c = migrarConfig({ foco: 50, descanso: 10, largo: 20, ciclos: 3, estilo: 'bloques_largos', tarea_id: '4', contexto_id: '', tema: 'Álgebra' });
    assert.deepEqual(c, { foco: 3000, descanso: 600, largo: 1200, ciclos: 3, estilo: 'bloques_largos', tarea_id: '4', contexto_id: '', tema: 'Álgebra' });
    // Un mínimo viejo de 1 minuto se conserva como 60 s.
    assert.equal(migrarConfig({ descanso: 1 }).descanso, 60);
});

prueba('la configuración ya en segundos no se multiplica otra vez', () => {
    assert.equal(migrarConfig({ foco: 5, descanso: 90, largo: 900, ciclos: 4, seg: true }).foco, 5);
    assert.equal(migrarConfig({ foco: 5, descanso: 90, largo: 900, ciclos: 4, seg: true }).descanso, 90);
});

prueba('una configuración vieja inválida vuelve a los valores por defecto', () => {
    assert.deepEqual(migrarConfig(null), CONFIG_POR_DEFECTO);
    assert.deepEqual(migrarConfig('basura'), CONFIG_POR_DEFECTO);
    assert.deepEqual(migrarConfig([1, 2]), CONFIG_POR_DEFECTO);
    const c = migrarConfig({ foco: 'abc', descanso: -3, largo: 999, ciclos: 1, estilo: { x: 1 } });
    assert.equal(c.foco, CONFIG_POR_DEFECTO.foco);
    assert.equal(c.descanso, CONFIG_POR_DEFECTO.descanso);
    assert.equal(c.largo, CONFIG_POR_DEFECTO.largo); // 999 min supera los 180 min
    assert.equal(c.ciclos, CONFIG_POR_DEFECTO.ciclos);
    assert.equal(c.estilo, 'clasico');
    assert.equal(migrarConfig({ foco: null }).foco, CONFIG_POR_DEFECTO.foco);
    assert.equal(migrarConfig({ foco: 0.5 }).foco, CONFIG_POR_DEFECTO.foco);
});

prueba('un estado en curso guardado en minutos se pasa a segundos una sola vez', () => {
    const viejo = { sesionId: 3, config: { foco: 25, descanso: 5, largo: 15, ciclos: 4 }, planificadoSeg: 1500, fase: 'foco' };
    const nuevo = migrarEstado(viejo);
    assert.deepEqual(nuevo.config, { foco: 1500, descanso: 300, largo: 900, ciclos: 4, seg: true });
    assert.equal(nuevo.planificadoSeg, 1500);
    assert.equal(migrarEstado(nuevo), nuevo);
    assert.equal(migrarEstado(null), null);
    // Un estado creado ahora ya viene marcado y no se toca.
    assert.equal(migrarEstado(crearEstado(1, corta, t0)).config.foco, 5);
});

prueba('un intervalo pendiente con planificado_min se pasa a planificado_seg', () => {
    assert.deepEqual(migrarEvento({ clave: '1-1', planificado_min: 25 }), { clave: '1-1', planificado_seg: 1500 });
    assert.deepEqual(migrarEvento({ clave: '1-2', planificado_min: null }), { clave: '1-2', planificado_seg: null });
    assert.deepEqual(migrarEvento({ clave: '1-3', planificado_seg: 5 }), { clave: '1-3', planificado_seg: 5 });
});

prueba('interpretarTiempo: un número solo son minutos y los sufijos s y m cambian la unidad', () => {
    assert.deepEqual(interpretarTiempo('25'), { ok: true, seg: 1500 });
    assert.deepEqual(interpretarTiempo(' 25 '), { ok: true, seg: 1500 });
    assert.deepEqual(interpretarTiempo('5'), { ok: true, seg: 300 });
    assert.deepEqual(interpretarTiempo('90s'), { ok: true, seg: 90 });
    assert.deepEqual(interpretarTiempo('5s'), { ok: true, seg: 5 });
    assert.deepEqual(interpretarTiempo('5 S'), { ok: true, seg: 5 });
    assert.deepEqual(interpretarTiempo('30seg'), { ok: true, seg: 30 });
    assert.deepEqual(interpretarTiempo('25m'), { ok: true, seg: 1500 });
    assert.deepEqual(interpretarTiempo('1m30s'), { ok: true, seg: 90 });
    assert.deepEqual(interpretarTiempo('1m30'), { ok: true, seg: 90 });
});

prueba('interpretarTiempo: con dos puntos es mm:ss o h:mm:ss', () => {
    assert.deepEqual(interpretarTiempo('25:00'), { ok: true, seg: 1500 });
    assert.deepEqual(interpretarTiempo('1:30'), { ok: true, seg: 90 });
    assert.deepEqual(interpretarTiempo('00:05'), { ok: true, seg: 5 });
    assert.deepEqual(interpretarTiempo('0:5'), { ok: true, seg: 5 });
    assert.deepEqual(interpretarTiempo('1:05:00'), { ok: true, seg: 3900 });
    assert.deepEqual(interpretarTiempo('2:59:59'), { ok: true, seg: 10799 });
    assert.deepEqual(interpretarTiempo('90:00'), { ok: true, seg: 5400 });
});

prueba('interpretarTiempo: los bordes son 5 s y 180:00', () => {
    assert.equal(interpretarTiempo('4s').ok, false);
    assert.equal(interpretarTiempo('0:04').ok, false);
    assert.equal(interpretarTiempo('0').ok, false);
    assert.equal(interpretarTiempo('0s').ok, false);
    assert.equal(interpretarTiempo('5s').ok, true);
    assert.equal(interpretarTiempo('0:05').ok, true);
    assert.deepEqual(interpretarTiempo('180:00'), { ok: true, seg: 10800 });
    assert.deepEqual(interpretarTiempo('3:00:00'), { ok: true, seg: 10800 });
    assert.deepEqual(interpretarTiempo('180'), { ok: true, seg: 10800 });
    assert.deepEqual(interpretarTiempo('10800s'), { ok: true, seg: 10800 });
    assert.equal(interpretarTiempo('180:01').ok, false);
    assert.equal(interpretarTiempo('3:00:01').ok, false);
    assert.equal(interpretarTiempo('181').ok, false);
    assert.equal(interpretarTiempo('10801s').ok, false);
    assert.equal(interpretarTiempo('4s').error, 'El tiempo va de 00:05 a 180:00.');
    assert.equal(interpretarTiempo('180:01').error, 'El tiempo va de 00:05 a 180:00.');
});

prueba('interpretarTiempo: rechaza entradas inválidas con un mensaje claro', () => {
    for (const texto of ['', '   ', 'abc', '-5', '1.5', '1,5', '25:', ':30', '1:2:3:4', '12:3x', 's', '25 min de foco', '1e3', null, undefined]) {
        const r = interpretarTiempo(texto);

        assert.equal(r.ok, false, `debería rechazar ${JSON.stringify(texto)}`);
        assert.match(r.error, /No entiendo ese tiempo/);
    }

    assert.match(interpretarTiempo('1:75').error, /00 a 59/);
    assert.match(interpretarTiempo('1:60:00').error, /00 a 59/);
    assert.match(interpretarTiempo('1:30:75').error, /00 a 59/);
    assert.match(interpretarTiempo('2m75s').error, /00 a 59/);
    assert.equal(interpretarTiempo('9999999').ok, false);
});

prueba('el anillo de progreso: completo al inicio, se vacía con el tiempo y se congela en pausa', () => {
    let e = crearEstado(1, config, t0);
    assert.equal(fraccionAnillo(e, t0), 1);
    assert.equal(fraccionAnillo(e, t0 + 10 * MIN), 0.6);
    assert.equal(fraccionAnillo(e, t0 + 25 * MIN), 0);
    assert.equal(fraccionAnillo(e, t0 + 90 * MIN), 0);
    e = pausar(e, t0 + 5 * MIN);
    assert.equal(fraccionAnillo(e, t0 + 60 * MIN), 0.8);
    e = reanudar(e, t0 + 30 * MIN);
    assert.equal(fraccionAnillo(e, t0 + 35 * MIN), 0.6);
});

prueba('el anillo queda completo sin sesión, en tiempo libre y con la fase reiniciada', () => {
    assert.equal(fraccionAnillo(null, t0), 1);
    let e = crearEstado(1, config, t0);
    e = avanzar(e, t0 + 27 * MIN).estado; // foco terminado, descanso en curso
    assert.equal(e.fase, DESCANSO);
    assert.equal(fraccionAnillo(e, t0 + 27 * MIN), 0.6);
    e = avanzar(e, t0 + 40 * MIN).estado;
    assert.equal(e.fase, LIBRE);
    assert.equal(fraccionAnillo(e, t0 + 50 * MIN), 1);
    const r = reiniciar(crearEstado(1, config, t0), t0 + 10 * MIN);
    assert.equal(fraccionAnillo(r, t0 + 10 * MIN), 1);
});

prueba('el desplazamiento del anillo es proporcional y se acota a 0..1', () => {
    assert.equal(desplazamientoAnillo(1, 100), 0);
    assert.equal(desplazamientoAnillo(0.25, 100), 75);
    assert.equal(desplazamientoAnillo(0, 100), 100);
    assert.equal(desplazamientoAnillo(2, 100), 0);
    assert.equal(desplazamientoAnillo(-1, 100), 100);
    assert.equal(desplazamientoAnillo(Number.NaN, 100), 0);
});

console.log(`\n${pruebas} pruebas correctas`);
