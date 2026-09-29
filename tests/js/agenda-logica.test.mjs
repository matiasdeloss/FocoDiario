// Verificación de la lógica pura de la Agenda. Se ejecuta con: node tests/js/agenda-logica.test.mjs
import assert from 'node:assert/strict';
import {
    ajustarLayout, alternarItem, cambiarTextoItem, contarItems, errorDeHoras, esperaDeReintento, horaValida, insertarItemDespues,
    listaATexto, lunesDe, moverLayout, normalizarItems, ordenarPorPosicion, quitarItem, redimensionarLayout, reordenar, sumarDias,
    textoALista,
} from '../../resources/js/agenda-logica.js';

let pruebas = 0;
const prueba = (nombre, fn) => { fn(); pruebas++; console.log('ok -', nombre); };

prueba('ajustarLayout deja la caja dentro de las 12 columnas', () => {
    assert.deepEqual(ajustarLayout({ x: 10, y: 3, ancho: 6, alto: 8 }), { x: 6, y: 3, ancho: 6, alto: 8 });
    assert.deepEqual(ajustarLayout({ x: -4, y: -1, ancho: 40, alto: 500 }), { x: 0, y: 0, ancho: 12, alto: 100 });
    assert.deepEqual(ajustarLayout({ x: 0, y: 0, ancho: 0, alto: 0 }), { x: 0, y: 0, ancho: 2, alto: 4 });
    assert.deepEqual(ajustarLayout({ x: 'a', y: null, ancho: '5', alto: 7.9 }), { x: 0, y: 0, ancho: 5, alto: 7 });
    assert.deepEqual(ajustarLayout(), { x: 0, y: 0, ancho: 6, alto: 8 });
});

prueba('mover y redimensionar respetan los límites de la hoja', () => {
    const caja = { x: 0, y: 0, ancho: 6, alto: 8 };

    assert.deepEqual(moverLayout(caja, -1, -1), caja);
    assert.deepEqual(moverLayout(caja, 1, 2), { x: 1, y: 2, ancho: 6, alto: 8 });
    assert.deepEqual(moverLayout({ x: 6, y: 0, ancho: 6, alto: 8 }, 1, 0).x, 6);
    // Ensanchar una caja pegada a la derecha la corre a la izquierda en lugar de salirse.
    assert.deepEqual(redimensionarLayout({ x: 6, y: 0, ancho: 6, alto: 8 }, 2, 0), { x: 4, y: 0, ancho: 8, alto: 8 });
    assert.deepEqual(redimensionarLayout(caja, -10, -10), { x: 0, y: 0, ancho: 2, alto: 4 });
});

prueba('ordenarPorPosicion lee de arriba abajo y de izquierda a derecha', () => {
    const cajas = [
        { id: 3, x: 0, y: 8 }, { id: 2, x: 6, y: 0 }, { id: 1, x: 0, y: 0 }, { id: 4, x: 0, y: 8 },
    ];

    assert.deepEqual(ordenarPorPosicion(cajas).map((c) => c.id), [1, 2, 3, 4]);
    // No modifica el original.
    assert.deepEqual(cajas.map((c) => c.id), [3, 2, 1, 4]);
});

prueba('reordenar intercambia los lugares y conserva el tamaño de cada caja', () => {
    const cajas = [
        { id: 1, x: 0, y: 0, ancho: 12, alto: 6 },
        { id: 2, x: 0, y: 8, ancho: 6, alto: 10 },
        { id: 3, x: 6, y: 8, ancho: 6, alto: 4 },
    ];

    const cambios = reordenar(cajas, 1, 1);

    assert.deepEqual(cambios.find((c) => c.id === 1), { id: 1, x: 0, y: 8, ancho: 12, alto: 6 });
    assert.deepEqual(cambios.find((c) => c.id === 2), { id: 2, x: 0, y: 0, ancho: 6, alto: 10 });
    assert.equal(cambios.find((c) => c.id === 3), undefined);

    // Bajar la última o subir la primera no cambia nada.
    assert.deepEqual(reordenar(cajas, 3, 1), []);
    assert.deepEqual(reordenar(cajas, 1, -1), []);
    assert.deepEqual(reordenar(cajas, 99, 1), []);
});

prueba('reordenar dos cajas lado a lado cambia su orden de lectura', () => {
    const cajas = [{ id: 1, x: 0, y: 0, ancho: 6, alto: 8 }, { id: 2, x: 6, y: 0, ancho: 6, alto: 8 }];
    const cambios = reordenar(cajas, 1, 1);

    assert.deepEqual(cambios.find((c) => c.id === 1), { id: 1, x: 6, y: 0, ancho: 6, alto: 8 });
    assert.deepEqual(cambios.find((c) => c.id === 2), { id: 2, x: 0, y: 0, ancho: 6, alto: 8 });
});

prueba('las operaciones de la lista no modifican la original', () => {
    const items = [{ texto: 'a', hecho: false }, { texto: 'b', hecho: true }];

    assert.deepEqual(alternarItem(items, 0), [{ texto: 'a', hecho: true }, { texto: 'b', hecho: true }]);
    assert.deepEqual(alternarItem(items, 1)[1], { texto: 'b', hecho: false });
    assert.deepEqual(cambiarTextoItem(items, 1, 'z')[1], { texto: 'z', hecho: true });
    assert.deepEqual(quitarItem(items, 0), [{ texto: 'b', hecho: true }]);
    assert.deepEqual(items, [{ texto: 'a', hecho: false }, { texto: 'b', hecho: true }]);
});

prueba('insertarItemDespues agrega un ítem vacío en el lugar pedido', () => {
    const items = [{ texto: 'a', hecho: false }, { texto: 'b', hecho: false }];

    assert.deepEqual(insertarItemDespues(items, 0).map((i) => i.texto), ['a', '', 'b']);
    assert.deepEqual(insertarItemDespues(items).map((i) => i.texto), ['a', 'b', '']);
    assert.deepEqual(insertarItemDespues([], 0), [{ texto: '', hecho: false }]);
    assert.deepEqual(insertarItemDespues(items, 50).map((i) => i.texto), ['a', 'b', '']);
});

prueba('normalizarItems tolera datos raros', () => {
    assert.deepEqual(normalizarItems(null), []);
    assert.deepEqual(normalizarItems('x'), []);
    assert.deepEqual(normalizarItems([{ texto: 5, hecho: 1 }, null, {}]), [
        { texto: '5', hecho: true }, { texto: '', hecho: false }, { texto: '', hecho: false },
    ]);
});

prueba('contarItems ignora renglones vacíos', () => {
    assert.deepEqual(contarItems([{ texto: 'a', hecho: true }, { texto: '  ', hecho: true }, { texto: 'b', hecho: false }]), { total: 2, hechos: 1 });
    assert.deepEqual(contarItems([]), { total: 0, hechos: 0 });
});

prueba('textoALista y listaATexto se pasan de un formato al otro', () => {
    assert.deepEqual(textoALista('- Leer resumen\n\n* Escuchar clase\n  Ver TP\r\n[ ] Repasar'), [
        { texto: 'Leer resumen', hecho: false },
        { texto: 'Escuchar clase', hecho: false },
        { texto: 'Ver TP', hecho: false },
        { texto: 'Repasar', hecho: false },
    ]);
    assert.deepEqual(textoALista(''), []);
    assert.deepEqual(textoALista(null), []);
    assert.equal(listaATexto([{ texto: 'a', hecho: true }, { texto: '', hecho: false }, { texto: 'b', hecho: false }]), 'a\nb');
});

prueba('horaValida acepta solo HH:MM de 24 horas', () => {
    for (const buena of ['00:00', '08:30', '18:00', '23:59']) assert.equal(horaValida(buena), true, buena);
    for (const mala of ['24:00', '9:00', '18:60', '18', '', null, undefined, '18:00:00', 'tarde']) assert.equal(horaValida(mala), false, String(mala));
});

prueba('errorDeHoras: las dos son opcionales, pero fin exige inicio y ser posterior', () => {
    assert.equal(errorDeHoras(null, null), null);
    assert.equal(errorDeHoras('', ''), null);
    assert.equal(errorDeHoras('18:00', null), null);
    assert.equal(errorDeHoras('18:00', '19:30'), null);
    assert.match(errorDeHoras(null, '19:30'), /primero/);
    assert.match(errorDeHoras('18:00', '17:59'), /posterior/);
    assert.match(errorDeHoras('18:00', '18:00'), /posterior/);
    assert.match(errorDeHoras('25:00', null), /inicio no es válida/);
    assert.match(errorDeHoras('18:00', '99:99'), /fin no es válida/);
});

prueba('lunesDe normaliza cualquier fecha al lunes de su semana', () => {
    assert.equal(lunesDe('2026-09-28'), '2026-09-28'); // lunes
    assert.equal(lunesDe('2026-09-30'), '2026-09-28'); // miércoles
    assert.equal(lunesDe('2026-10-04'), '2026-09-28'); // domingo: sigue en la semana que termina
    assert.equal(lunesDe('2026-10-05'), '2026-10-05');
    assert.equal(lunesDe('2027-01-01'), '2026-12-28'); // cruce de año
    assert.equal(lunesDe('2024-03-01'), '2024-02-26'); // año bisiesto
});

prueba('lunesDe rechaza fechas inválidas', () => {
    for (const mala of ['', 'hoy', '2026-02-31', '2026-13-01', '28/09/2026', null, undefined]) assert.equal(lunesDe(mala), null, String(mala));
});

prueba('sumarDias cruza meses y años', () => {
    assert.equal(sumarDias('2026-09-28', 7), '2026-10-05');
    assert.equal(sumarDias('2026-09-28', -7), '2026-09-21');
    assert.equal(sumarDias('2026-12-31', 1), '2027-01-01');
    assert.equal(sumarDias('mal', 1), null);
});

prueba('esperaDeReintento crece y termina', () => {
    assert.equal(esperaDeReintento(0), 1000);
    assert.equal(esperaDeReintento(1), 3000);
    assert.equal(esperaDeReintento(2), 8000);
    assert.equal(esperaDeReintento(3), null);
    assert.equal(esperaDeReintento(-1), null);
    assert.equal(esperaDeReintento(0, [5]), 5);
});

console.log(`\n${pruebas} pruebas de agenda-logica en verde`);
