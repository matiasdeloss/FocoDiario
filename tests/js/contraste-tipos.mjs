// Verifica la paleta de los 5 tipos (tarea, recordatorio, nota, estudio, planner): texto/fondo >= 4.5, borde/punto >= 3
// contra el fondo del tipo y contra el papel. Los valores deben coincidir con los tokens --tipo-*-* de app.css.
// Uso: node tests/js/contraste-tipos.mjs   (sale con código 1 si algún par no llega)
const hex = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
const lum = (h) => {
    const [r, g, b] = hex(h).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const ratio = (a, b) => { const [l1, l2] = [lum(a), lum(b)].sort((x, y) => y - x); return (l1 + 0.05) / (l2 + 0.05); };
const hue = (h) => {
    const [r, g, b] = hex(h).map((v) => v / 255);
    const mx = Math.max(r, g, b), mn = Math.min(r, g, b), d = mx - mn;
    if (!d) return 0;
    const t = mx === r ? ((g - b) / d) % 6 : mx === g ? (b - r) / d + 2 : (r - g) / d + 4;
    return Math.round(((t * 60) + 360) % 360);
};
const papel = '#f9f4ed', fondoApp = '#f5ead8';
const TIPOS = {
    tarea:        { fondo: '#ffe1d0', texto: '#643312', borde: '#b2622d' },
    recordatorio: { fondo: '#cdebc5', texto: '#1c4a29', borde: '#3a8748' },
    nota:         { fondo: '#d9e4f7', texto: '#20407a', borde: '#4a72b8' },
    sesion:       { fondo: '#f0d3e9', texto: '#591f4c', borde: '#98479a' },
    planner:      { fondo: '#e1ddf6', texto: '#2f2670', borde: '#6a5bc0' },
};
let bajos = 0;
const fila = (n, r, min) => { const ok = r >= min; if (!ok) bajos++; console.log(n.padEnd(52) + r.toFixed(2).padEnd(8) + String(min).padEnd(6) + (ok ? 'OK' : 'no llega')); };
for (const [t, c] of Object.entries(TIPOS)) {
    console.log(`\n${t} (hue borde ${hue(c.borde)}°)`);
    fila('  texto sobre fondo del tipo', ratio(c.texto, c.fondo), 4.5);
    fila('  texto sobre papel', ratio(c.texto, papel), 4.5);
    fila('  borde/punto sobre fondo del tipo', ratio(c.borde, c.fondo), 3);
    fila('  borde/punto sobre papel', ratio(c.borde, papel), 3);
    fila('  borde/punto sobre fondo de la app', ratio(c.borde, fondoApp), 3);
    fila('  punto sobre día seleccionado (#a75b27)? fondo claro', ratio(c.fondo, '#a75b27'), 3);
}
console.log('\nSeparación de hues (borde) entre tipos y contra prioridad/estado (rojo 8°, ámbar 45°, azul 190°):');
const ref = { rojo: 8, ambar: 45, azul: 190 };
const hs = Object.entries(TIPOS).map(([t, c]) => [t, hue(c.borde)]);
for (let i = 0; i < hs.length; i++) {
    for (let j = i + 1; j < hs.length; j++) { const d = Math.abs(hs[i][1] - hs[j][1]); console.log(`  ${hs[i][0]}-${hs[j][0]}: ${Math.min(d, 360 - d)}°`); }
    for (const [n, h] of Object.entries(ref)) { const d = Math.abs(hs[i][1] - h); console.log(`  ${hs[i][0]} vs ${n}: ${Math.min(d, 360 - d)}°`); }
}
console.log('\n' + bajos + ' par(es) no llegan al mínimo.');
process.exit(bajos ? 1 : 0);
