// Verificación de la lógica de fases del temporizador. Se ejecuta con: node tests/js/pomodoro-logica.test.mjs
import assert from 'node:assert/strict';
import {
    avanzar, crearEstado, DESCANSO, FOCO, formatearTiempo, iniciarSiguienteFoco, LIBRE,
    pausar, reanudar, reiniciar, restanteMs, saltar, terminar, transcurridoMs,
} from '../../resources/js/pomodoro-logica.js';

const MIN = 60_000;
const config = { foco: 25, descanso: 5, largo: 15, ciclos: 4 };
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
        { tipo: eventos[0].tipo, inicio: eventos[0].inicio, fin: eventos[0].fin, completado: eventos[0].completado, plan: eventos[0].planificado_min },
        { tipo: FOCO, inicio: t0, fin: t0 + 25 * MIN, completado: true, plan: 25 },
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
    const c = { foco: 25, descanso: 5, largo: 15, ciclos: 2 };
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

console.log(`\n${pruebas} pruebas correctas`);
