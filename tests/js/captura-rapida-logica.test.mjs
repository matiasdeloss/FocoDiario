// Verificación de la lógica pura de la nota rápida de Hoy. Se ejecuta con: node tests/js/captura-rapida-logica.test.mjs
import assert from 'node:assert/strict';
import { chipVisible, etiquetaFecha, fechaEnDias, fechaISO, resumenTarea, tipoConColor, tipoValido } from '../../resources/js/captura-rapida-logica.js';

let pruebas = 0;
const prueba = (nombre, fn) => { fn(); pruebas++; console.log('ok -', nombre); };
const hoy = new Date(2026, 8, 30, 15, 0);

prueba('fechaISO y fechaEnDias usan la hora local y cruzan de mes y de año', () => {
    assert.equal(fechaISO(hoy), '2026-09-30');
    assert.equal(fechaEnDias(0, hoy), '2026-09-30');
    assert.equal(fechaEnDias(1, hoy), '2026-10-01');
    assert.equal(fechaEnDias(1, new Date(2026, 11, 31)), '2027-01-01');
});

prueba('etiquetaFecha: Hoy, Mañana y fecha corta', () => {
    assert.equal(etiquetaFecha('2026-09-30', '', hoy), 'Hoy');
    assert.equal(etiquetaFecha('2026-10-01', '', hoy), 'Mañana');
    assert.equal(etiquetaFecha('2026-10-12', '', hoy), '12 oct');
    assert.equal(etiquetaFecha('2026-09-29', '', hoy), '29 sep');
});

prueba('etiquetaFecha: con hora y con otro año', () => {
    assert.equal(etiquetaFecha('2026-10-12', '10:00', hoy), '12 oct 10:00');
    assert.equal(etiquetaFecha('2026-10-01', '09:30', hoy), 'Mañana 09:30');
    assert.equal(etiquetaFecha('2027-01-05', '', hoy), '5 ene 2027');
});

prueba('etiquetaFecha: valores vacíos o inválidos dan texto vacío', () => {
    assert.equal(etiquetaFecha('', '10:00', hoy), '');
    assert.equal(etiquetaFecha(undefined, '', hoy), '');
    assert.equal(etiquetaFecha('2026-02-31', '', hoy), '');
    assert.equal(etiquetaFecha('12/10/2026', '', hoy), '');
    assert.equal(etiquetaFecha('2026-10-12', '25', hoy), '12 oct');
});

prueba('chipVisible y tipoValido', () => {
    assert.equal(chipVisible('nota', 'nota'), true);
    assert.equal(chipVisible('nota', 'tarea'), false);
    assert.equal(chipVisible('tarea recordatorio nota', 'recordatorio'), true);
    assert.equal(tipoValido('tarea'), 'tarea');
    assert.equal(tipoValido('evento'), 'nota');
    assert.equal(tipoValido(null), 'nota');
});

prueba('resumenTarea: prioridad distinta de media y contexto', () => {
    assert.equal(resumenTarea({ prioridad: 'media', etiquetaPrioridad: 'Media', contexto: '' }), '');
    assert.equal(resumenTarea({ prioridad: 'alta', etiquetaPrioridad: 'Alta', contexto: '' }), 'Alta');
    assert.equal(resumenTarea({ prioridad: 'alta', etiquetaPrioridad: 'Alta', contexto: ' Tesis ' }), 'Alta · Tesis');
    assert.equal(resumenTarea({ contexto: 'Tesis' }), 'Tesis');
    assert.equal(resumenTarea(), '');
});

prueba('tipoConColor: notas y tareas, no recordatorios', () => {
    assert.equal(tipoConColor('nota'), true);
    assert.equal(tipoConColor('tarea'), true);
    assert.equal(tipoConColor('recordatorio'), false);
});

console.log(`${pruebas} pruebas correctas`);
