import assert from 'node:assert/strict';
import { test } from 'node:test';
import { datosConColumnaVigente, fichaDeTarjeta, urlDeMovimiento } from '../../resources/js/tablero-logica.js';
import { candidatas, desvincular, idsDe, vincular } from '../../resources/js/notas-vinculadas-logica.js';

test('la ficha de una tarjeta distingue notas y tareas', () => {
    assert.equal(fichaDeTarjeta('nota', '5'), 'nota:5');
    assert.equal(fichaDeTarjeta('tarea', 12), 'tarea:12');
    assert.equal(fichaDeTarjeta(undefined, 3), 'tarea:3');
});

test('cada tipo de tarjeta se mueve con su ruta', () => {
    const rutas = { urlColumna: '/tareas/__ID__/columna', urlColumnaNota: '/notas/__ID__/columna' };

    assert.equal(urlDeMovimiento('tarea', 7, rutas), '/tareas/7/columna');
    assert.equal(urlDeMovimiento('nota', '9', rutas), '/notas/9/columna');
});

test('vincular no repite notas y desvincular las quita por id', () => {
    const a = { id: 1, titulo: 'A' };
    const b = { id: 2, titulo: 'B' };

    assert.deepEqual(vincular([a], b).map((n) => n.id), [1, 2]);
    assert.deepEqual(vincular([a], a).map((n) => n.id), [1]);
    assert.deepEqual(desvincular([a, b], 1).map((n) => n.id), [2]);
    assert.deepEqual(idsDe([a, b]), [1, 2]);
});

test('los resultados de la búsqueda no ofrecen las notas ya vinculadas', () => {
    const resultados = [{ id: 1, titulo: 'A' }, { id: 2, titulo: 'B' }];

    assert.deepEqual(candidatas(resultados, [{ id: 1, titulo: 'A' }]).map((n) => n.id), [2]);
});

test('al editar una nota del tablero manda la columna donde está la tarjeta, no la del data-nota viejo', () => {
    const datos = { id: 4, titulo: 'N', columna_id: 1 };

    assert.equal(datosConColumnaVigente(datos, '3').columna_id, 3);
    assert.equal(datos.columna_id, 1);
    assert.equal(datosConColumnaVigente(datos, undefined), datos);
    assert.equal(datosConColumnaVigente(datos, 'x'), datos);
});
