// Verificación de la lógica pura de las listas de Hoy (tareas y recordatorios). Se ejecuta con: node tests/js/hoy-lista-logica.test.mjs
import assert from 'node:assert/strict';
import {
    crearSerie, estadoFinal, estaMarcado, marcadoDeTarea, pendientesFinales, puedeAlternar, textoPendientes,
} from '../../resources/js/hoy-lista-logica.js';

let pruebas = 0;
const prueba = async (nombre, fn) => { await fn(); pruebas++; console.log('ok -', nombre); };
const pausa = (ms) => new Promise((resolver) => setTimeout(resolver, ms));

await prueba('la insignia usa singular, plural y "Todo al día" con cero', () => {
    assert.equal(textoPendientes(0), 'Todo al día');
    assert.equal(textoPendientes(-2), 'Todo al día');
    assert.equal(textoPendientes(1), '1 pendiente');
    assert.equal(textoPendientes(2), '2 pendientes');
    assert.equal(textoPendientes(12), '12 pendientes');
});

await prueba('aria-checked se lee como booleano sin confundir "false" con verdadero', () => {
    assert.equal(estaMarcado('true'), true);
    assert.equal(estaMarcado('false'), false);
    assert.equal(estaMarcado(null), false);
    assert.equal(estaMarcado(true), true);
});

await prueba('bug doble envío: una fila con petición en curso no se puede alternar de nuevo', () => {
    assert.equal(puedeAlternar(true), false);
    assert.equal(puedeAlternar(false), true);
});

await prueba('bug estado que no vuelve atrás: si falla, la fila regresa al estado previo', () => {
    assert.equal(estadoFinal({ previo: false, pedido: true, ok: false }), false);
    assert.equal(estadoFinal({ previo: true, pedido: false, ok: false }), true);
    assert.equal(estadoFinal({ previo: false, pedido: true, ok: true }), true);
});

await prueba('el estado del servidor es la fuente de verdad, incluso si contradice lo pedido', () => {
    assert.equal(estadoFinal({ previo: false, pedido: true, ok: true, servidor: false }), false);
    assert.equal(estadoFinal({ previo: true, pedido: false, ok: true, servidor: true }), true);
    assert.equal(marcadoDeTarea('completada'), true);
    assert.equal(marcadoDeTarea('pendiente'), false);
    assert.equal(marcadoDeTarea('en_progreso'), false);
    assert.equal(marcadoDeTarea(undefined), undefined);
});

await prueba('bug contador desincronizado: manda el total del servidor; sin él nunca baja de cero', () => {
    assert.equal(pendientesFinales(5, 2), 5);
    assert.equal(pendientesFinales(0, 3), 0);
    assert.equal(pendientesFinales(undefined, 3), 3);
    assert.equal(pendientesFinales(undefined, -1), 0);
    assert.equal(pendientesFinales('4', 1), 1);
    assert.equal(pendientesFinales(-3, 2), 2);
});

await prueba('bug respuestas cruzadas: la serie ejecuta de a una y en orden de llegada', async () => {
    const serie = crearSerie();
    const bitacora = [];
    const lenta = serie.agregar(async () => { bitacora.push('a-inicio'); await pausa(30); bitacora.push('a-fin'); });
    const rapida = serie.agregar(async () => { bitacora.push('b-inicio'); await pausa(1); bitacora.push('b-fin'); });

    assert.equal(serie.pendientes, 2);
    await Promise.all([lenta, rapida]);
    assert.deepEqual(bitacora, ['a-inicio', 'a-fin', 'b-inicio', 'b-fin']);
    assert.equal(serie.pendientes, 0);
});

await prueba('solo la última petición de la cola redibuja la lista (evita parpadeos y listas viejas)', async () => {
    const serie = crearSerie();
    const redibujos = [];
    const tarea = (nombre) => async ({ esUltima }) => { await pausa(2); redibujos.push([nombre, esUltima()]); };

    await Promise.all([serie.agregar(tarea('uno')), serie.agregar(tarea('dos')), serie.agregar(tarea('tres'))]);

    assert.deepEqual(redibujos, [['uno', false], ['dos', false], ['tres', true]]);
    assert.equal(serie.pendientes, 0);
});

await prueba('un error de red en una petición no frena a las siguientes de la cola', async () => {
    const serie = crearSerie();
    const bitacora = [];
    const fallida = serie.agregar(async () => { throw new Error('red'); });
    const siguiente = serie.agregar(async () => { bitacora.push('sigue'); return 'ok'; });

    await assert.rejects(fallida, /red/);
    assert.equal(await siguiente, 'ok');
    assert.deepEqual(bitacora, ['sigue']);
    assert.equal(serie.pendientes, 0);
});

await prueba('tras la última petición la serie queda libre y la próxima vuelve a ser "última"', async () => {
    const serie = crearSerie();
    let ultima;

    await serie.agregar(async ({ esUltima }) => { ultima = esUltima(); });
    assert.equal(ultima, true);
    await serie.agregar(async ({ esUltima }) => { ultima = esUltima(); });
    assert.equal(ultima, true);
});

console.log(`${pruebas} pruebas correctas`);
