import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const js = readFileSync(new URL('../../resources/js/calendario.js', import.meta.url), 'utf8');
const css = readFileSync(new URL('../../resources/css/calendario.css', import.meta.url), 'utf8');

test('los eventos con hora sin fin duran una hora y se fuerza la duración', () => {
    assert.match(js, /defaultTimedEventDuration:\s*'01:00:00'/);
    assert.match(js, /forceEventDuration:\s*true/);
});

test('los eventos de la grilla horaria tienen alto mínimo legible y no se tapan entre sí', () => {
    const minimo = Number(js.match(/eventMinHeight:\s*(\d+)/)?.[1]);
    assert.ok(minimo >= 44, `eventMinHeight ${minimo} es muy chico`);
    assert.match(js, /slotEventOverlap:\s*false/);
    assert.match(css, /\.fc \.fc-timegrid-event\s*\{[^}]*min-height:\s*\d+px/);
});

test('el título del evento horario puede mostrarse en más de una línea', () => {
    assert.match(css, /\.fc-timegrid-event \.fc-event-title\s*\{[^}]*white-space:\s*normal/);
});
