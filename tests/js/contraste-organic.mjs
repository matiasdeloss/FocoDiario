// Verifica la relación de contraste WCAG de los pares texto/fondo del tema Organic.
// Uso: node tests/js/contraste-organic.mjs   (sale con código 1 si algún par no llega al mínimo)
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
    a100: '#fff2eb', a200: '#ffe1d0', a300: '#ffc6a5', a400: '#f6a06b', a500: '#d67f48', a600: '#b2622d', a700: '#8c491a', a800: '#643312', a900: '#402310', accent: '#c67139',
    s100: '#f0fae1', s200: '#e1eecc', s300: '#ccdbb2', s600: '#728157', s700: '#56633f', s800: '#3d472b', s900: '#272e1b',
};
// Colores nuevos de app.css (deben coincidir con los valores declarados allí)
const N = {
    rojoFondo: '#f8dcd5', rojoTexto: '#8a2a1f', rojoBorde: '#b3402f',
    ambarFondo: '#f6e4b3', ambarTexto: '#6b4700',
    azulFondo: '#dbe8e9', azulTexto: '#27525f',
};
// Cuarto tono del calendario (sesión de estudio): ocre, mezcla de las rampas terracota y salvia
N.sesionFondo = mezcla(T.a300, T.s300, 0.4);
N.sesionTexto = mezcla(T.a900, T.s900, 0.5);
N.sesionBorde = mezcla(T.a700, T.s700, 0.5);
// Relleno del botón primario: terracota 600 oscurecido un 30% hacia el 700 (el 600 puro da 4,49:1 con blanco)
N.botonPrimario = mezcla(T.a600, T.a700, 0.7);

const pares = [
    ['Texto sobre fondo', T.text, T.bg, 4.5],
    ['Texto sobre papel', T.text, T.n100, 4.5],
    ['Texto suave (n700) sobre papel', T.n700, T.n100, 4.5],
    ['Texto suave (n700) sobre fondo', T.n700, T.bg, 4.5],
    ['Texto suave (n700) sobre papel hundido', T.n700, T.n200, 4.5],
    ['Acento 700 (texto) sobre papel', T.a700, T.n100, 4.5],
    ['Acento 700 (texto) sobre fondo', T.a700, T.bg, 4.5],
    ['Acento 700 sobre acento 100 (nav activo)', T.a700, T.a100, 4.5],
    ['Acento 700 sobre acento 200', T.a700, T.a200, 4.5],
    ['Acento base sobre papel (solo iconos/grande)', T.accent, T.n100, 3],
    ['Boton primario: blanco sobre relleno (600/700)', '#ffffff', N.botonPrimario, 4.5],
    ['(descartado) blanco sobre acento 600 puro', '#ffffff', T.a600, 4.5],
    ['Boton primario hover: blanco sobre acento 700', '#ffffff', T.a700, 4.5],
    ['Boton primario activo: blanco sobre acento 800', '#ffffff', T.a800, 4.5],
    ['(descartado) blanco sobre acento base', '#ffffff', T.accent, 4.5],
    ['Borde de control (n600) sobre papel', T.n600, T.n100, 3],
    ['Borde de control (n600) sobre input n200', T.n600, T.n200, 3],
    ['Anillo de foco (acento base) sobre fondo', T.accent, T.bg, 3],
    ['Badge rojo', N.rojoTexto, N.rojoFondo, 4.5],
    ['Badge ambar', N.ambarTexto, N.ambarFondo, 4.5],
    ['Badge verde (salvia 800 sobre 200)', T.s800, T.s200, 4.5],
    ['Badge azul', N.azulTexto, N.azulFondo, 4.5],
    ['Badge gris (n800 sobre n300)', T.n800, T.n300, 4.5],
    ['Texto rojo sobre papel (fecha vencida)', N.rojoTexto, T.n100, 4.5],
    ['Aviso: acento 800 sobre acento 100', T.a800, T.a100, 4.5],
    ['Calendario tarea: a800 sobre a200', T.a800, T.a200, 4.5],
    ['Calendario recordatorio: s800 sobre s200', T.s800, T.s200, 4.5],
    ['Calendario nota: n800 sobre n200', T.n800, T.n200, 4.5],
    ['Calendario sesion (ocre)', N.sesionTexto, N.sesionFondo, 4.5],
    ['Borde tarea (a600) sobre a200', T.a600, T.a200, 3],
    ['Borde recordatorio (s600) sobre s200', T.s600, T.s200, 3],
    ['Borde nota (n600) sobre n200', T.n600, T.n200, 3],
    ['Borde sesion sobre fondo sesion', N.sesionBorde, N.sesionFondo, 3],
    ['Salvia 700 (texto) sobre papel', T.s700, T.n100, 4.5],
    ['Texto suave sobre papel hundido tablero', T.n700, T.n200, 4.5],
    ['Foco (texto) sobre acento suave del filtro', T.text, T.a100, 4.5],
];

let fallos = 0;
console.log('Par'.padEnd(52) + 'Relacion'.padEnd(10) + 'Minimo  Resultado');
for (const [nombre, fg, bg, min] of pares) {
    const r = ratio(fg, bg);
    const ok = r >= min;
    if (!ok && !nombre.startsWith('(descartado)')) fallos++;
    console.log(nombre.padEnd(52) + r.toFixed(2).padEnd(10) + String(min).padEnd(8) + (ok ? 'OK' : nombre.startsWith('(descartado)') ? 'no llega (descartado)' : 'FALLA'));
}
console.log('\nRelleno del boton primario: ' + N.botonPrimario);
console.log('Ocre de sesion:fondo ' + N.sesionFondo + ' texto ' + N.sesionTexto + ' borde ' + N.sesionBorde);
process.exit(fallos ? 1 : 0);
