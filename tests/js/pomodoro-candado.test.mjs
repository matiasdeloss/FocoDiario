// Verificación del candado entre pestañas. Se ejecuta con: node tests/js/pomodoro-candado.test.mjs
import assert from 'node:assert/strict';
import { adquirirCandado, liberarCandado, TTL_CANDADO_MS } from '../../resources/js/pomodoro-candado.js';
import { avanzar, crearEstado, DESCANSO, describir, FOCO, LIBRE, pausar } from '../../resources/js/pomodoro-logica.js';

const memoria = () => {
    const datos = new Map();

    return { getItem: (k) => datos.get(k) ?? null, setItem: (k, v) => datos.set(k, String(v)), removeItem: (k) => datos.delete(k) };
};
const K = 'candado';
const MIN = 60_000;
let pruebas = 0;
const prueba = (nombre, fn) => { fn(); pruebas++; console.log('ok -', nombre); };

prueba('la primera pestaña toma el candado y la segunda no', () => {
    const a = memoria();
    assert.equal(adquirirCandado(a, K, 'A', 1000), true);
    assert.equal(adquirirCandado(a, K, 'B', 1500), false);
    assert.equal(adquirirCandado(a, K, 'A', 1600), true);
});

prueba('el candado caduca y otra pestaña toma el relevo', () => {
    const a = memoria();
    adquirirCandado(a, K, 'A', 1000);
    assert.equal(adquirirCandado(a, K, 'B', 1000 + TTL_CANDADO_MS + 1), true);
    assert.equal(adquirirCandado(a, K, 'A', 1000 + TTL_CANDADO_MS + 2), false);
});

prueba('el dueño renueva sin reescribir mientras falta bastante', () => {
    const a = memoria();
    adquirirCandado(a, K, 'A', 1000);
    const antes = a.getItem(K);
    adquirirCandado(a, K, 'A', 1200);
    assert.equal(a.getItem(K), antes);
    adquirirCandado(a, K, 'A', 1000 + TTL_CANDADO_MS - 100);
    assert.notEqual(a.getItem(K), antes);
});

prueba('liberar solo suelta el candado propio', () => {
    const a = memoria();
    adquirirCandado(a, K, 'A', 1000);
    liberarCandado(a, K, 'B');
    assert.equal(adquirirCandado(a, K, 'B', 1100), false);
    liberarCandado(a, K, 'A');
    assert.equal(adquirirCandado(a, K, 'B', 1200), true);
});

prueba('sin almacenamiento o con datos rotos no bloquea', () => {
    const roto = { getItem: () => { throw new Error('no'); }, setItem: () => { throw new Error('no'); }, removeItem: () => {} };
    assert.equal(adquirirCandado(roto, K, 'A', 1000), true);
    const basura = memoria();
    basura.setItem(K, '{no es json');
    assert.equal(adquirirCandado(basura, K, 'A', 1000), true);
});

prueba('dos pestañas que reconstruyen el mismo estado generan los mismos eventos con la misma clave', () => {
    const config = { foco: 1500, descanso: 300, largo: 900, ciclos: 4 };
    const t0 = Date.UTC(2026, 8, 29, 12, 0, 0);
    const guardado = JSON.parse(JSON.stringify(crearEstado(7, config, t0))); // lo que dejó otra pestaña en localStorage
    const a = avanzar(guardado, t0 + 26 * MIN);
    const b = avanzar(JSON.parse(JSON.stringify(guardado)), t0 + 26 * MIN);
    assert.deepEqual(a.eventos.map((e) => e.clave), ['7-1']);
    assert.deepEqual(a.eventos, b.eventos);
});

prueba('describir: nombre, tiempo y progreso de cada fase', () => {
    const config = { foco: 1500, descanso: 300, largo: 900, ciclos: 4 };
    const t0 = Date.UTC(2026, 8, 29, 12, 0, 0);
    const e = crearEstado(1, config, t0);
    const foco = describir(e, t0 + 5 * MIN);
    assert.equal(foco.fase, FOCO);
    assert.equal(foco.corta, 'Foco');
    assert.equal(foco.texto, '20:00');
    assert.equal(foco.progreso, 0.2);
    assert.equal(describir(pausar(e, t0 + MIN), t0 + 9 * MIN).pausado, true);
    const desc = describir(avanzar(e, t0 + 26 * MIN).estado, t0 + 26 * MIN);
    assert.equal(desc.fase, DESCANSO);
    assert.equal(desc.nombre, 'Descanso corto');
    const libre = describir(avanzar(e, t0 + 40 * MIN).estado, t0 + 40 * MIN);
    assert.equal(libre.fase, LIBRE);
    assert.equal(libre.progreso, null);
    assert.equal(libre.texto, '10:00');
});

console.log(`${pruebas} pruebas correctas`);
