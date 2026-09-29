/*
 * Piezas compartidas por el planner y la hoja del día: el guardador (con su indicador "Guardado")
 * y el editor de cajas. Un solo guardador por página, para que todo el guardado pase por el mismo indicador.
 */
import { crearGuardador } from './agenda-guardado.js';
import { enviarCambios } from './agenda-red.js';
import { crearEditorDeCajas } from './agenda-cajas.js';

const TEXTOS = {
    pendiente: 'Editando…',
    guardando: 'Guardando…',
    guardado: 'Guardado',
    reintentando: 'Sin conexión, reintentando…',
    error: 'No se pudo guardar',
};

/** Muestra el estado del guardado en los indicadores de la página. Los avisos importantes van a un aviso para lectores de pantalla. */
export function mostrarEstado(estado, detalle = null) {
    document.querySelectorAll('[data-guardado]').forEach((indicador) => {
        const texto = indicador.querySelector('[data-guardado-texto]');
        const boton = indicador.querySelector('[data-guardado-reintentar]');
        const aviso = indicador.querySelector('[data-guardado-aviso]');
        const mensaje = estado === 'error' && detalle ? `${TEXTOS.error}: ${detalle}` : TEXTOS[estado];

        indicador.dataset.estado = estado;
        texto.textContent = mensaje;

        if (boton) {
            boton.hidden = estado !== 'error';
        }

        // Solo los errores (y que se resolvieron) se anuncian: guardar mientras se escribe no debe interrumpir la lectura.
        if (aviso && (estado === 'error' || estado === 'reintentando')) {
            aviso.textContent = mensaje;
        } else if (aviso && estado === 'guardado' && aviso.textContent !== '') {
            aviso.textContent = 'Los cambios se guardaron.';
        }
    });
}

export const guardador = crearGuardador({
    enviar: (clave, cambios) => enviarCambios(clave, cambios),
    alEstado: mostrarEstado,
});

let clasesActividad = {};

try {
    clasesActividad = JSON.parse(document.querySelector('[data-actividades]')?.dataset.actividades ?? '{}');
} catch {
    clasesActividad = {};
}

export const editor = crearEditorDeCajas({ guardador, clasesActividad });

document.addEventListener('click', (evento) => {
    if (evento.target.closest('[data-guardado-reintentar]')) {
        guardador.reintentar();
    }
});

// Al salir de la página se manda lo que falte; si hubo un error de guardado, el navegador avisa antes de perder texto.
window.addEventListener('pagehide', () => {
    guardador.vaciar();
});

window.addEventListener('beforeunload', (evento) => {
    if (guardador.estado() === 'error') {
        evento.preventDefault();
        evento.returnValue = '';
    }
});
