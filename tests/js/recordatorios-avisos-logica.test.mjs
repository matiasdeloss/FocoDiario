// Verificación de la lógica pura de los toasts de recordatorios. Se ejecuta con: node tests/js/recordatorios-avisos-logica.test.mjs
import assert from 'node:assert/strict';
import { agregarCerrado, claveDe, detalleDe, planificar } from '../../resources/js/recordatorios-avisos-logica.js';

let pruebas = 0;
const prueba = async (nombre, fn) => { await fn(); pruebas++; console.log('ok -', nombre); };

const r = (id, recordarEn, extra = {}) => ({ id, recordar_en: recordarEn, hora: recordarEn.slice(11), ...extra });

await prueba('la clave combina id y hora programada', () => {
    assert.equal(claveDe(r(12, '2026-09-30T14:55')), '12@2026-09-30T14:55');
});

await prueba('muestra los vencidos que no están abiertos ni cerrados', () => {
    const vencidos = [r(1, '2026-09-30T14:00'), r(2, '2026-09-30T14:30'), r(3, '2026-09-30T14:50')];
    const { mostrar, quitar } = planificar(vencidos, ['2@2026-09-30T14:30'], new Set(['3@2026-09-30T14:50']));

    assert.deepEqual(mostrar.map((x) => x.id), [1]);
    assert.deepEqual(quitar, []);
});

await prueba('un recordatorio pospuesto vuelve a mostrarse aunque se haya cerrado con la hora anterior', () => {
    const { mostrar } = planificar([r(2, '2026-09-30T14:40')], ['2@2026-09-30T14:30'], new Set());

    assert.deepEqual(mostrar.map((x) => x.id), [2]);
});

await prueba('quita los toasts que ya no vencen o que se cerraron en otra pestaña', () => {
    const abiertas = new Set(['1@2026-09-30T14:00', '2@2026-09-30T14:30', '3@2026-09-30T14:50']);
    const vencidos = [r(2, '2026-09-30T14:30'), r(3, '2026-09-30T14:50')];
    const { mostrar, quitar } = planificar(vencidos, ['3@2026-09-30T14:50'], abiertas);

    assert.deepEqual(mostrar, []);
    assert.deepEqual(quitar, ['1@2026-09-30T14:00', '3@2026-09-30T14:50']);
});

await prueba('el detalle lleva la hora y la descripción recortada en una línea', () => {
    assert.equal(detalleDe(r(1, '2026-09-30T14:00')), '14:00');
    assert.equal(detalleDe(r(1, '2026-09-30T14:00', { descripcion: '  Llevar\n el  DNI ' })), '14:00 · Llevar el DNI');

    const larga = detalleDe(r(1, '2026-09-30T14:00', { descripcion: 'a'.repeat(200) }));

    assert.ok(larga.endsWith('…'));
    assert.equal(larga.length, '14:00 · '.length + 80);
});

await prueba('los cerrados no se repiten y se conservan solo los más recientes', () => {
    let cerrados = [];

    cerrados = agregarCerrado(cerrados, 'a', 3);
    cerrados = agregarCerrado(cerrados, 'b', 3);
    cerrados = agregarCerrado(cerrados, 'a', 3);
    assert.deepEqual(cerrados, ['b', 'a']);

    cerrados = agregarCerrado(cerrados, 'c', 3);
    cerrados = agregarCerrado(cerrados, 'd', 3);
    assert.deepEqual(cerrados, ['a', 'c', 'd']);

    // Ids que parecen números no se reordenan (a diferencia de las claves de un objeto).
    assert.deepEqual(agregarCerrado(['12@x', '5@y'], '1@z', 2), ['5@y', '1@z']);
});

console.log(`\n${pruebas} pruebas correctas`);
