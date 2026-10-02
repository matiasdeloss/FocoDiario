{{-- Nota rápida: captura de una tarea, un recordatorio o una nota. Requiere: $destinos. Opcionales: $guardada (Nota recién guardada por la ruta de notas), $valores. --}}
<section id="nota-rapida" class="hoy-tarjeta hoy-nota" aria-labelledby="nota-rapida-titulo" data-nota-rapida>
    {{-- Con JavaScript los chips reemplazan a los paneles abiertos; sin él, los campos quedan a la vista. --}}
    <script nonce="{{ Vite::cspNonce() }}">document.documentElement.classList.add('js-hoy');</script>
    {{-- La cabecera (título y colores) vive dentro del formulario: los colores son parte de lo que se envía. --}}
    @include('hoy._captura-form', ['guardada' => $guardada ?? null, 'valores' => $valores ?? []])

    {{-- Resumen de errores de la captura (lo llena la respuesta HTMX). Las confirmaciones salen como toast. --}}
    <div id="nota-rapida-aviso" class="hoy-aviso-caja" role="status" aria-live="polite"></div>
</section>
