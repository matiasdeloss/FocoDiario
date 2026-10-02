import test from 'node:test';
import assert from 'node:assert/strict';
import { tiposIniciales } from '../../resources/js/calendario-filtros-logica.js';

const TIPOS = ['tarea', 'recordatorio', 'nota', 'sesion', 'planner'];

test('sin filtro guardado se muestran todos los tipos', () => {
    assert.deepEqual(tiposIniciales(null, null, TIPOS), TIPOS);
    assert.deepEqual(tiposIniciales('basura', null, TIPOS), TIPOS);
});

test('un filtro guardado antes de existir el planner lo activa por defecto', () => {
    assert.deepEqual(tiposIniciales(['tarea', 'nota'], null, TIPOS), ['tarea', 'nota', 'planner']);
});

test('lo que el usuario ya vio y desmarcó sigue desmarcado', () => {
    assert.deepEqual(tiposIniciales(['tarea'], TIPOS, TIPOS), ['tarea']);
    assert.deepEqual(tiposIniciales([], TIPOS, TIPOS), []);
});

test('ignora tipos desconocidos en lo guardado', () => {
    assert.deepEqual(tiposIniciales(['tarea', 'otro'], TIPOS, TIPOS), ['tarea']);
});

test('un usuario antiguo que había desmarcado todo ve solo el planner nuevo', () => {
    assert.deepEqual(tiposIniciales([], null, TIPOS), ['planner']);
});
