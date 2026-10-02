// Verificación de la lógica del tema. Se ejecuta con: node tests/js/tema-logica.test.mjs
import assert from 'node:assert/strict';
import { CLAVE_TEMA, temaEfectivo, temaGuardado } from '../../resources/js/tema-logica.js';

let pruebas = 0;
const prueba = (nombre, fn) => { fn(); pruebas++; console.log('ok -', nombre); };

prueba('la clave de almacenamiento es foco-tema', () => {
    assert.equal(CLAVE_TEMA, 'foco-tema');
});

prueba('solo light y dark cuentan como elección guardada', () => {
    assert.equal(temaGuardado('light'), 'light');
    assert.equal(temaGuardado('dark'), 'dark');
    assert.equal(temaGuardado('sepia'), null);
    assert.equal(temaGuardado(''), null);
    assert.equal(temaGuardado(null), null);
});

prueba('sin elección guardada se sigue al sistema', () => {
    assert.equal(temaEfectivo(null, true), 'dark');
    assert.equal(temaEfectivo(null, false), 'light');
    assert.equal(temaEfectivo('basura', true), 'dark');
});

prueba('la elección guardada gana sobre el sistema', () => {
    assert.equal(temaEfectivo('light', true), 'light');
    assert.equal(temaEfectivo('dark', false), 'dark');
});
