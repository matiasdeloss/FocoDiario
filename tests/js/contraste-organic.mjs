// Verifica la relación de contraste WCAG de los pares texto/fondo del tema Organic.
// Uso: node tests/js/contraste-organic.mjs   (informa los contrastes reales; no falla)
const hex = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
const mezcla = (a, b, pctA) => {
    const [x, y] = [hex(a), hex(b)];
    return '#' + x.map((v, i) => Math.round(v * pctA + y[i] * (1 - pctA)).toString(16).padStart(2, '0')).join('');
};
const lum = (h) => {
    const [r, g, b] = hex(h).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const ratio = (a, b) => { const [l1, l2] = [lum(a), lum(b)].sort((x, y) => y - x); return (l1 + 0.05) / (l2 + 0.05); };

// Tokens de organic.css
const T = {
    bg: '#f5ead8', text: '#201e1d',
    n100: '#f9f4ed', n200: '#eee7db', n300: '#dcd3c4', n400: '#c0b6a5', n500: '#a19786', n600: '#82796a', n700: '#645c50', n800: '#474238', n900: '#2e2b25',
    a100: '#fff2eb', a200: '#ffe1d0', a300: '#ffc6a5', a400: '#f6a06b', a500: '#d67f48', a600: '#b2622d', a700: '#8c491a', a800: '#643312', a900: '#402310', accent: '#c67139', boton: '#a75b27',
    s100: '#f0fae1', s200: '#e1eecc', s300: '#ccdbb2', s500: '#8fa073', s600: '#728157', s700: '#56633f', s800: '#3d472b', s900: '#272e1b',
};
// Colores nuevos de app.css (deben coincidir con los valores declarados allí)
const N = {
    rojoFondo: '#f8dcd5', rojoTexto: '#8a2a1f', rojoBorde: '#b3402f',
    ambarFondo: '#f6e4b3', ambarTexto: '#6b4700',
    azulFondo: '#dbe8e9', azulTexto: '#27525f',
};
// Sesión de estudio: ciruela (paleta de tipos completa en tests/js/contraste-tipos.mjs)
N.sesionFondo = '#f0d3e9';
N.sesionTexto = '#591f4c';
N.sesionBorde = '#98479a';

// Los pares "del diseño" son los valores exactos del diseño Hoy v2 (pueden quedar bajo el mínimo WCAG:
// se informan para que el usuario decida). Los demás son pares que la app usa por su cuenta.
const blanco = '#ffffff';
const pares = [
    // --- Pares del diseño Hoy v2 (copiados tal cual)
    ['[diseño] Botón/día activo con el acento base (sustituido por --color-accent-boton)', blanco, T.accent, 4.5],
    ['[diseño] Botón hover con acento 600 (sustituido por acento 700)', blanco, T.a600, 4.5],
    // --- Botón principal (token --color-accent-boton, más oscuro que el acento base)
    ['Botón principal: blanco sobre --color-accent-boton', blanco, T.boton, 4.5],
    ['Botón principal hover: blanco sobre acento 700', blanco, T.a700, 4.5],
    ['Botón principal pressed: blanco sobre acento 800', blanco, T.a800, 4.5],
    ['Día seleccionado: blanco sobre --color-accent-boton', blanco, T.boton, 4.5],
    ['[diseño] Kicker/metadatos: n600 sobre fondo', T.n600, T.bg, 4.5],
    ['[diseño] Metadatos: n600 sobre papel', T.n600, T.n100, 4.5],
    ['[diseño] Fase del anillo (8px): n600 sobre papel', T.n600, T.n100, 4.5],
    ['[diseño] Texto secundario: n700 sobre papel', T.n700, T.n100, 4.5],
    ['[diseño] Fecha: n700 sobre fondo', T.n700, T.bg, 4.5],
    ['[diseño] Modo activo: a700 sobre papel', T.a700, T.n100, 4.5],
    ['[diseño] Modo inactivo: n700 sobre fondo', T.n700, T.bg, 4.5],
    ['[diseño] Etiqueta sugerencia: a800 sobre a200', T.a800, T.a200, 4.5],
    ['[diseño] Etiqueta información: s800 sobre s200', T.s800, T.s200, 4.5],
    ['[diseño] Placeholder (n600) sobre fondo (campo)', T.n600, T.bg, 4.5],
    ['[diseño] Marca de check: blanco sobre salvia 500', blanco, T.s500, 4.5],
    ['[diseño] Anillo del check (n500) sobre papel (no texto)', T.n500, T.n100, 3],
    ['[diseño] Pista del anillo Pomodoro: n200 sobre papel (no texto)', T.n200, T.n100, 3],
    ['[diseño] Progreso del anillo: acento sobre papel (no texto)', T.accent, T.n100, 3],
    ['[diseño] Punto de ronda vacío: n300 sobre papel (no texto)', T.n300, T.n100, 3],
    ['Ícono más/menos de recomendación: n700 sobre papel', T.n700, T.n100, 3],
    ['[diseño] Enlace: a700 sobre papel', T.a700, T.n100, 4.5],
    ['[diseño] Foco de campos: acento sobre fondo (no texto)', T.accent, T.bg, 3],
    ['[diseño] Texto sobre fondo', T.text, T.bg, 4.5],
    ['[diseño] Texto sobre papel', T.text, T.n100, 4.5],
    // --- Resto de la app
    ['Acento 700 sobre acento 200 (pestañas/filtros)', T.a700, T.a200, 4.5],
    ['Acento 700 sobre fondo (pestaña activa)', T.a700, T.bg, 4.5],
    ['Badge rojo', N.rojoTexto, N.rojoFondo, 4.5],
    ['Badge ámbar', N.ambarTexto, N.ambarFondo, 4.5],
    ['Badge verde (salvia 800 sobre 200)', T.s800, T.s200, 4.5],
    ['Badge azul', N.azulTexto, N.azulFondo, 4.5],
    ['Badge gris (n800 sobre n200)', T.n800, T.n200, 4.5],
    ['Texto rojo sobre papel (fecha vencida)', N.rojoTexto, T.n100, 4.5],
    ['Calendario tarea: a800 sobre a200', T.a800, T.a200, 4.5],
    ['Calendario recordatorio: s800 sobre s200', T.s800, T.s200, 4.5],
    ['Calendario nota: n800 sobre n200', T.n800, T.n200, 4.5],
    ['Calendario sesión (ciruela)', N.sesionTexto, N.sesionFondo, 4.5],
    ['Borde tarea (a600) sobre a200', T.a600, T.a200, 3],
    ['Borde recordatorio (s600) sobre s200', T.s600, T.s200, 3],
    ['Borde nota (n600) sobre n200', T.n600, T.n200, 3],
    ['Borde sesión sobre fondo sesión', N.sesionBorde, N.sesionFondo, 3],
    ['Blanco sobre acento 700 (hover peligro/alerta)', blanco, T.a700, 4.5],
    ['Blanco sobre rojo borde (hover peligro)', blanco, N.rojoBorde, 4.5],
    ['Mini-temporizador: fase azul sobre papel', N.azulTexto, T.n100, 4.5],
    ['Mini-temporizador: fase ámbar sobre papel', N.ambarTexto, T.n100, 4.5],
    ['Salvia 700 sobre papel', T.s700, T.n100, 4.5],
    ['Texto n700 sobre n200 (hover de filas)', T.n700, T.n200, 4.5],
    ['Texto n600 sobre n200 (hover de filas)', T.n600, T.n200, 4.5],
];

console.log('Par'.padEnd(66) + 'Relación'.padEnd(10) + 'Mínimo  Resultado');
let bajos = 0;
for (const [nombre, fg, bg, min] of pares) {
    const r = ratio(fg, bg);
    const ok = r >= min;
    if (!ok) bajos++;
    console.log(nombre.padEnd(66) + r.toFixed(2).padEnd(10) + String(min).padEnd(8) + (ok ? 'OK' : 'no llega'));
}
console.log('\n' + bajos + ' par(es) no llegan al mínimo WCAG (informativo: los valores [diseño] son los del diseño Hoy v2).');
console.log('Ciruela de sesión: fondo ' + N.sesionFondo + ' texto ' + N.sesionTexto + ' borde ' + N.sesionBorde);
process.exit(0);
