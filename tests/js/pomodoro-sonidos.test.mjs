// Verificación del catálogo de sonidos del temporizador. Se ejecuta con: node tests/js/pomodoro-sonidos.test.mjs
import assert from 'node:assert/strict';
import { SONIDOS, sonidoValido, TONOS_SONIDO } from '../../resources/js/pomodoro-logica.js';

const prueba = (nombre, fn) => { fn(); console.log('ok -', nombre); };
const fin = (tonos) => Math.max(...tonos.map((t) => t.inicio + t.duracion));

prueba('el catálogo tiene cinco sonidos con claves únicas y todos tienen tonos', () => {
    assert.equal(SONIDOS.length, 5);
    assert.equal(new Set(SONIDOS.map((s) => s.clave)).size, 5);
    SONIDOS.forEach((s) => {
        assert.ok(s.nombre && s.descripcion);
        assert.ok(TONOS_SONIDO[s.clave].length > 0);
    });
    assert.deepEqual(SONIDOS.map((s) => s.clave).sort(), ['alarma', 'campana', 'digital', 'marimba', 'suave']);
});

prueba('sonidoValido cae en la campana con valores nulos o desconocidos', () => {
    assert.equal(sonidoValido('alarma'), 'alarma');
    assert.equal(sonidoValido('suave'), 'suave');
    assert.equal(sonidoValido('digital'), 'digital');
    assert.equal(sonidoValido('marimba'), 'marimba');
    ['nada', null, undefined, '', 5, true].forEach((v) => assert.equal(sonidoValido(v), 'campana'));
});

prueba('cada tono tiene frecuencia, duración y ganancia finitas y positivas, y ganancia razonable', () => {
    Object.values(TONOS_SONIDO).flat().forEach((t) => {
        [t.frecuencia, t.duracion, t.ganancia].forEach((n) => assert.ok(Number.isFinite(n) && n > 0));
        assert.ok(t.ganancia <= 0.25);
        assert.ok(['sine', 'triangle', 'square', 'sawtooth'].includes(t.onda));
        assert.ok(t.duracion > 0.04, 'más largo que el ataque y la caída');
    });
});

prueba('los inicios son no negativos y van ordenados', () => {
    Object.values(TONOS_SONIDO).forEach((tonos) => {
        tonos.forEach((t) => assert.ok(t.inicio >= 0));
        tonos.slice(1).forEach((t, i) => assert.ok(t.inicio >= tonos[i].inicio));
    });
});

prueba('la alarma dura entre 2 y 5 segundos y tiene al menos 4 pitidos', () => {
    const alarma = TONOS_SONIDO.alarma;

    assert.ok(alarma.length >= 4);
    assert.ok(fin(alarma) >= 2 && fin(alarma) <= 5);
    assert.ok(new Set(alarma.map((t) => t.frecuencia)).size >= 2, 'alterna alturas');
});

prueba('los sonidos breves duran menos de dos segundos', () => {
    assert.ok(fin(TONOS_SONIDO.campana) < 1);
    assert.ok(fin(TONOS_SONIDO.suave) < 2);
    assert.ok(fin(TONOS_SONIDO.digital) < 1);
    assert.ok(fin(TONOS_SONIDO.marimba) < 1.5);
});
