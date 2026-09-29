// Verificación del autoguardado de la Agenda (debounce, reintentos, errores). Se ejecuta con: node tests/js/agenda-guardado.test.mjs
import assert from 'node:assert/strict';
import { crearGuardador } from '../../resources/js/agenda-guardado.js';

let pruebas = 0;
const prueba = async (nombre, fn) => { await fn(); pruebas++; console.log('ok -', nombre); };
const pausa = (ms) => new Promise((resolver) => setTimeout(resolver, ms));

function armar(enviar, opciones = {}) {
    const estados = [];
    const guardador = crearGuardador({
        enviar,
        alEstado: (estado, detalle) => estados.push([estado, detalle]),
        espera: 20,
        esperasReintento: [15, 15],
        ...opciones,
    });

    return { guardador, estados, nombres: () => estados.map(([estado]) => estado) };
}

await prueba('junta los cambios rápidos de una caja en un solo envío', async () => {
    const envios = [];
    const { guardador, nombres } = armar(async (clave, cambios) => { envios.push([clave, cambios]); });

    guardador.programar('PATCH /c/1', { titulo: 'N' });
    guardador.programar('PATCH /c/1', { titulo: 'Ne' });
    guardador.programar('PATCH /c/1', { contenido: 'texto' });
    await pausa(80);

    assert.equal(envios.length, 1);
    assert.deepEqual(envios[0], ['PATCH /c/1', { titulo: 'Ne', contenido: 'texto' }]);
    assert.deepEqual(nombres(), ['pendiente', 'pendiente', 'pendiente', 'guardando', 'guardado']);
    assert.equal(guardador.hayCambiosSinGuardar(), false);
});

await prueba('cada caja se guarda por separado', async () => {
    const envios = [];
    const { guardador } = armar(async (clave, cambios) => { envios.push([clave, cambios]); });

    guardador.programar('PATCH /c/1', { titulo: 'A' });
    guardador.programar('PATCH /c/2', { titulo: 'B' });
    await pausa(80);

    assert.deepEqual(envios.map(([clave]) => clave).sort(), ['PATCH /c/1', 'PATCH /c/2']);
});

await prueba('lo escrito mientras se envía se manda después y en orden', async () => {
    const envios = [];
    let liberar;
    const { guardador } = armar(async (clave, cambios) => {
        envios.push(cambios);
        if (envios.length === 1) await new Promise((resolver) => { liberar = resolver; });
    });

    guardador.programar('K', { contenido: 'uno' });
    await pausa(50); // ya salió el primero y sigue en vuelo
    guardador.programar('K', { contenido: 'dos' });
    await pausa(50); // el segundo espera: no se envía en paralelo
    assert.equal(envios.length, 1);

    liberar();
    await pausa(50);

    assert.deepEqual(envios, [{ contenido: 'uno' }, { contenido: 'dos' }]);
});

await prueba('si falla, reintenta y termina guardando', async () => {
    let intentos = 0;
    const { guardador, nombres } = armar(async () => {
        intentos++;
        if (intentos < 3) throw Object.assign(new Error('x'), { reintentable: true, mensaje: 'Sin conexión' });
    });

    guardador.programar('K', { titulo: 'A' });
    await pausa(200);

    assert.equal(intentos, 3);
    assert.ok(nombres().includes('reintentando'));
    assert.equal(nombres().at(-1), 'guardado');
});

await prueba('sin más reintentos avisa el error y "reintentar" lo vuelve a mandar', async () => {
    let fallar = true;
    const cuerpos = [];
    const { guardador, estados } = armar(async (clave, cambios) => {
        cuerpos.push(cambios);
        if (fallar) throw Object.assign(new Error('x'), { reintentable: true, mensaje: 'No hay conexión' });
    });

    guardador.programar('K', { titulo: 'A' });
    await pausa(200);

    assert.equal(cuerpos.length, 3); // el envío inicial y dos reintentos
    assert.deepEqual(estados.at(-1), ['error', 'No hay conexión']);
    assert.equal(guardador.hayCambiosSinGuardar(), true);

    fallar = false;
    await guardador.reintentar();

    assert.equal(guardador.estado(), 'guardado');
    assert.equal(guardador.hayCambiosSinGuardar(), false);
    assert.deepEqual(cuerpos.at(-1), { titulo: 'A' });
});

await prueba('un error de validación no se reintenta', async () => {
    let intentos = 0;
    const { guardador, estados } = armar(async () => {
        intentos++;
        throw Object.assign(new Error('x'), { reintentable: false, mensaje: 'La hora de fin tiene que ser posterior.' });
    });

    guardador.programar('K', { hora_fin: '10:00' });
    await pausa(120);

    assert.equal(intentos, 1);
    assert.deepEqual(estados.at(-1), ['error', 'La hora de fin tiene que ser posterior.']);
});

await prueba('lo escrito durante un fallo pisa lo que falló', async () => {
    const cuerpos = [];
    let intentos = 0;
    const { guardador } = armar(async (clave, cambios) => {
        cuerpos.push(cambios);
        intentos++;
        if (intentos === 1) {
            guardador.programar('K', { titulo: 'nuevo' });
            throw Object.assign(new Error('x'), { reintentable: true });
        }
    });

    guardador.programar('K', { titulo: 'viejo', contenido: 'c' });
    await pausa(200);

    assert.deepEqual(cuerpos.at(-1), { titulo: 'nuevo', contenido: 'c' });
});

await prueba('vaciar manda ya lo que está esperando', async () => {
    const envios = [];
    const { guardador } = armar(async (clave, cambios) => { envios.push(cambios); }, { espera: 10_000 });

    guardador.programar('K', { titulo: 'A' });
    await guardador.vaciar();

    assert.deepEqual(envios, [{ titulo: 'A' }]);
    assert.equal(guardador.estado(), 'guardado');
});

await prueba('descartar olvida los cambios de una caja borrada', async () => {
    const envios = [];
    const { guardador } = armar(async (clave, cambios) => { envios.push(cambios); });

    guardador.programar('K', { titulo: 'A' });
    guardador.descartar('K');
    await pausa(80);

    assert.deepEqual(envios, []);
    assert.equal(guardador.hayCambiosSinGuardar(), false);
});

console.log(`\n${pruebas} pruebas de agenda-guardado en verde`);
