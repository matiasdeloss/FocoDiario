// Verificación de la lógica pura de la tarjeta Pomodoro de Hoy. Se ejecuta con: node tests/js/hoy-pomodoro-logica.test.mjs
import assert from 'node:assert/strict';
import { avanzar, crearEstado, DESCANSO, describir, FOCO, LIBRE, pausar } from '../../resources/js/pomodoro-logica.js';
import {
    actualizarContador, botonPrincipal, CIRCUNFERENCIA, contadorInicial, desplazamientoAnillo, etiquetaFase, minutosDeModo,
    modoDeEstado, puntosLlenos,
} from '../../resources/js/hoy-pomodoro-logica.js';

const MIN = 60_000;
const config = { foco: 25, descanso: 5, largo: 15, ciclos: 4 };
let pruebas = 0;
const prueba = (nombre, fn) => { fn(); pruebas++; console.log('ok -', nombre); };

prueba('crearEstado arranca en foco por defecto y en descanso corto o largo si se pide', () => {
    const foco = crearEstado(1, config, 0);
    const corto = crearEstado(1, config, 0, 'descanso');
    const largo = crearEstado(1, config, 0, 'largo');

    assert.equal(foco.fase, FOCO);
    assert.equal(foco.planificadoSeg, 25 * 60);
    assert.equal(corto.fase, DESCANSO);
    assert.equal(corto.planificadoSeg, 5 * 60);
    assert.equal(corto.descansoLargo, false);
    assert.equal(largo.fase, DESCANSO);
    assert.equal(largo.planificadoSeg, 15 * 60);
    assert.equal(largo.descansoLargo, true);
});

prueba('un descanso iniciado directamente pasa a tiempo libre sin contar pomodoros', () => {
    const { estado, eventos } = avanzar(crearEstado(1, config, 0, 'descanso'), 6 * MIN);

    assert.equal(estado.fase, LIBRE);
    assert.equal(estado.completados, 0);
    assert.equal(eventos.length, 1);
    assert.equal(eventos[0].tipo, 'descanso');
    assert.equal(eventos[0].planificado_min, 5);
});

prueba('el modo del control segmentado sale de la fase (el tiempo libre cuenta como descanso)', () => {
    assert.equal(modoDeEstado(crearEstado(1, config, 0)), 'foco');
    assert.equal(modoDeEstado(crearEstado(1, config, 0, 'descanso')), 'descanso');
    assert.equal(modoDeEstado(crearEstado(1, config, 0, 'largo')), 'largo');
    assert.equal(modoDeEstado(avanzar(crearEstado(1, config, 0, 'descanso'), 6 * MIN).estado), 'descanso');
});

prueba('minutosDeModo usa la configuración guardada', () => {
    assert.equal(minutosDeModo(config, 'foco'), 25);
    assert.equal(minutosDeModo(config, 'descanso'), 5);
    assert.equal(minutosDeModo(config, 'largo'), 15);
    assert.equal(minutosDeModo({ ...config, foco: 50 }, 'foco'), 50);
});

prueba('el anillo está lleno al empezar, medio vacío a la mitad y vacío al final', () => {
    assert.equal(desplazamientoAnillo(0), 0);
    assert.ok(Math.abs(desplazamientoAnillo(0.5) - CIRCUNFERENCIA / 2) < 1e-9);
    assert.ok(Math.abs(desplazamientoAnillo(1) - CIRCUNFERENCIA) < 1e-9);
    assert.equal(desplazamientoAnillo(null), 0);
    assert.equal(desplazamientoAnillo(-3), 0);
    assert.ok(Math.abs(desplazamientoAnillo(7) - CIRCUNFERENCIA) < 1e-9);
    assert.ok(Math.abs(CIRCUNFERENCIA - 270.18) < 0.01);
});

prueba('el progreso del anillo sigue al motor (pausa incluida)', () => {
    const estado = crearEstado(1, config, 0);

    assert.equal(describir(estado, 5 * MIN).progreso, 0.2);
    assert.ok(Math.abs(desplazamientoAnillo(describir(estado, 5 * MIN).progreso) - CIRCUNFERENCIA * 0.2) < 1e-9);
    assert.equal(describir(pausar(estado, 5 * MIN), 20 * MIN).progreso, 0.2);
});

prueba('los cuatro puntos se llenan hasta el tope', () => {
    assert.equal(puntosLlenos(0), 0);
    assert.equal(puntosLlenos(3), 3);
    assert.equal(puntosLlenos(4), 4);
    assert.equal(puntosLlenos(9), 4);
});

prueba('las etiquetas de fase', () => {
    assert.equal(etiquetaFase(null, false, 'foco'), 'ENFOQUE');
    assert.equal(etiquetaFase(null, false, 'largo'), 'PAUSA LARGA');
    assert.equal(etiquetaFase(crearEstado(1, config, 0), false, 'foco'), 'ENFOCADO');
    assert.equal(etiquetaFase(crearEstado(1, config, 0), true, 'foco'), 'EN PAUSA');
    assert.equal(etiquetaFase(crearEstado(1, config, 0, 'descanso'), false, 'descanso'), 'DESCANSO');
    assert.equal(etiquetaFase(crearEstado(1, config, 0, 'largo'), false, 'largo'), 'PAUSA LARGA');
    assert.equal(etiquetaFase(avanzar(crearEstado(1, config, 0, 'descanso'), 6 * MIN).estado, false, 'descanso'), 'TIEMPO LIBRE');
});

prueba('el botón principal cambia según la fase', () => {
    assert.deepEqual(botonPrincipal(null, false), { texto: 'Iniciar', accion: 'iniciar' });
    assert.deepEqual(botonPrincipal(crearEstado(1, config, 0), false), { texto: 'Pausar', accion: 'pausar' });
    assert.deepEqual(botonPrincipal(crearEstado(1, config, 0), true), { texto: 'Reanudar', accion: 'reanudar' });
    assert.deepEqual(botonPrincipal(avanzar(crearEstado(1, config, 0, 'descanso'), 6 * MIN).estado, false), { texto: 'Siguiente foco', accion: 'siguiente-foco' });
});

prueba('el contador suma solo los pomodoros que terminan mientras la página está abierta', () => {
    // Al abrir Hoy ya había una sesión con 2 completados: eso ya está en el total de la base.
    let c = contadorInicial({ sesionId: 7, completados: 2 });

    assert.equal(c.extra, 0);
    c = actualizarContador(c, { sesionId: 7, completados: 2 });
    assert.equal(c.extra, 0);
    c = actualizarContador(c, { sesionId: 7, completados: 3 });
    assert.equal(c.extra, 1);
    c = actualizarContador(c, { sesionId: 7, completados: 3 });
    assert.equal(c.extra, 1);

    // La sesión termina y se empieza otra: sus pomodoros son nuevos.
    c = actualizarContador(c, null);
    assert.equal(c.extra, 1);
    c = actualizarContador(c, { sesionId: 8, completados: 0 });
    c = actualizarContador(c, { sesionId: 8, completados: 1 });
    assert.equal(c.extra, 2);
});

prueba('sin sesión al abrir, el primer pomodoro cuenta', () => {
    let c = contadorInicial(null);

    c = actualizarContador(c, { sesionId: 1, completados: 0 });
    c = actualizarContador(c, { sesionId: 1, completados: 1 });
    assert.equal(c.extra, 1);
});

console.log(`${pruebas} pruebas de la tarjeta Pomodoro de Hoy pasaron`);
