// Contraste de la paleta de actividades de la agenda (tokens de resources/css/organic.css). Se ejecuta con: node tests/js/contraste-agenda.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../../resources/css/organic.css', import.meta.url), 'utf8');
const token = (nombre) => css.match(new RegExp('--' + nombre + String.raw`:\s*(#[0-9a-fA-F]{6});`))?.[1];

const luminancia = (hex) => {
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
        .map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const contraste = (a, b) => {
    const [claro, oscuro] = [luminancia(a), luminancia(b)].sort((x, y) => y - x);

    return (claro + 0.05) / (oscuro + 0.05);
};

const claves = ['terracota', 'salvia', 'ocre', 'azul-polvo', 'ciruela', 'rosa', 'oliva', 'arena', 'celeste', 'lavanda'];
const papel = token('color-neutral-100');
let filas = 0;

for (const clave of claves) {
    const fondo = token(`actividad-${clave}-fondo`);
    const acento = token(`actividad-${clave}-acento`);
    const texto = token(`actividad-${clave}-texto`);

    assert.ok(fondo && acento && texto, `faltan tokens de ${clave}`);

    const textoSobreFondo = contraste(texto, fondo);
    const textoSobrePapel = contraste(texto, papel);
    const acentoSobrePapel = contraste(acento, papel);

    // Texto de título y de líneas del planner: AA (4,5:1). El filete y el punto son gráficos: 3:1.
    assert.ok(textoSobreFondo >= 4.5, `${clave}: texto sobre fondo ${textoSobreFondo.toFixed(2)}`);
    assert.ok(textoSobrePapel >= 4.5, `${clave}: texto sobre papel ${textoSobrePapel.toFixed(2)}`);
    assert.ok(acentoSobrePapel >= 3, `${clave}: acento sobre papel ${acentoSobrePapel.toFixed(2)}`);
    console.log(`ok - ${clave.padEnd(11)} texto/fondo ${textoSobreFondo.toFixed(1)}  texto/papel ${textoSobrePapel.toFixed(1)}  acento/papel ${acentoSobrePapel.toFixed(1)}`);
    filas++;
}

assert.equal(filas, 10);
console.log('\n10 colores de actividad cumplen el contraste');
