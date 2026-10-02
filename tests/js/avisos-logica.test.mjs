// Verificación de la lógica de los avisos flotantes. Se ejecuta con: node tests/js/avisos-logica.test.mjs
import assert from 'node:assert/strict';
import {
    MAX_VISIBLES, VARIANTES, avisosDeEvento, claveDeAviso, configuracion, indiceARetirar, normalizarTipo,
} from '../../resources/js/avisos-logica.js';

let pruebas = 0;
const prueba = (nombre, fn) => { fn(); pruebas++; console.log('ok -', nombre); };

prueba('hay una variante por tipo de mensaje', () => {
    assert.deepEqual(Object.keys(VARIANTES), ['exito', 'info', 'aviso', 'error', 'recordatorio']);
    Object.values(VARIANTES).forEach((variante) => assert.match(variante.icono, /^[a-z0-9-]+$/));
});

prueba('duraciones: éxito e info 5 s, aviso 7 s, error 8 s y el recordatorio queda fijo', () => {
    assert.equal(VARIANTES.exito.duracion, 5000);
    assert.equal(VARIANTES.info.duracion, 5000);
    assert.equal(VARIANTES.aviso.duracion, 7000);
    assert.equal(VARIANTES.error.duracion, 8000);
    assert.equal(VARIANTES.recordatorio.duracion, null);
});

prueba('solo el error usa role=alert', () => {
    assert.equal(VARIANTES.error.rol, 'alert');
    ['exito', 'info', 'aviso', 'recordatorio'].forEach((tipo) => assert.equal(VARIANTES[tipo].rol, 'status'));
});

prueba('un tipo desconocido cae en info', () => {
    assert.equal(normalizarTipo('exito'), 'exito');
    assert.equal(normalizarTipo('peligro'), 'info');
    assert.equal(normalizarTipo(undefined), 'info');
    assert.equal(normalizarTipo('toString'), 'info');
});

prueba('la configuración usa la variante y deja pisar duración e ícono', () => {
    assert.deepEqual(configuracion('error'), { tipo: 'error', rol: 'alert', icono: 'exclamation-octagon-fill', duracion: 8000 });
    assert.equal(configuracion('info', { duracion: 15000 }).duracion, 15000);
    assert.equal(configuracion('info', { duracion: null }).duracion, null);
    assert.equal(configuracion('info', { icono: 'sun-fill' }).icono, 'sun-fill');
    assert.equal(configuracion('raro').tipo, 'info');
});

prueba('pasado el límite se retira el más viejo que no sea fijo', () => {
    assert.equal(MAX_VISIBLES, 4);
    const lista = (...fijos) => fijos.map((fijo) => ({ fijo }));

    assert.equal(indiceARetirar(lista(false, false, false, false)), -1);
    assert.equal(indiceARetirar(lista(false, false, false, false, false)), 0);
    assert.equal(indiceARetirar(lista(true, false, false, false, false)), 1);
});

prueba('si todos los anteriores son fijos no se retira ninguno, ni el recién llegado', () => {
    assert.equal(indiceARetirar([{ fijo: true }, { fijo: true }, { fijo: true }, { fijo: true }, { fijo: false }]), -1);
});

prueba('el evento admite un aviso, un arreglo y el formato { value: [...] } de HTMX', () => {
    const uno = { tipo: 'exito', texto: 'Tarea creada.' };

    assert.deepEqual(avisosDeEvento(uno), [{ tipo: 'exito', texto: 'Tarea creada.', detalle: null }]);
    assert.deepEqual(avisosDeEvento({ value: [uno, { tipo: 'error', texto: 'Falló.', detalle: 'Probá de nuevo.' }] }), [
        { tipo: 'exito', texto: 'Tarea creada.', detalle: null },
        { tipo: 'error', texto: 'Falló.', detalle: 'Probá de nuevo.' },
    ]);
    assert.deepEqual(avisosDeEvento([uno]), [{ tipo: 'exito', texto: 'Tarea creada.', detalle: null }]);
});

prueba('el evento descarta avisos sin texto y normaliza el tipo', () => {
    assert.deepEqual(avisosDeEvento(null), []);
    assert.deepEqual(avisosDeEvento('hola'), []);
    assert.deepEqual(avisosDeEvento({ tipo: 'exito', texto: '   ' }), []);
    assert.deepEqual(avisosDeEvento({ value: 'Tarea creada.' }), []);
    assert.deepEqual(avisosDeEvento({ tipo: 'raro', texto: 'Hola.' }), [{ tipo: 'info', texto: 'Hola.', detalle: null }]);
});

prueba('la clave de repetición mezcla tipo y texto', () => {
    assert.equal(claveDeAviso('exito', 'Hola.'), 'exito|Hola.');
    assert.notEqual(claveDeAviso('exito', 'Hola.'), claveDeAviso('error', 'Hola.'));
});

console.log(`${pruebas} pruebas de avisos-logica correctas`);
