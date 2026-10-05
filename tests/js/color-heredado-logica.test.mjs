import assert from 'node:assert/strict';
import { test } from 'node:test';
import { pistaDeColor } from '../../resources/js/color-heredado-logica.js';

test('con color propio no hay pista', () => {
    assert.equal(pistaDeColor({ colorPropio: '#c0663a', clave: 'salvia', origen: 'Carrera' }), null);
});

test('sin color propio y con color heredado muestra de qué contexto viene', () => {
    assert.deepEqual(pistaDeColor({ colorPropio: '', clave: 'azul-polvo', origen: 'Programación II' }), {
        texto: 'Usa el color de Programación II',
        fondo: 'var(--actividad-azul-polvo-fondo)',
        marca: 'var(--actividad-azul-polvo-acento)',
    });
});

test('sin color heredado no hay pista', () => {
    assert.equal(pistaDeColor({ colorPropio: '', clave: '', origen: '' }), null);
    assert.equal(pistaDeColor(), null);
});
