{{-- Contenido de una caja sobre hoja con renglones: texto libre o lista con casillas. Requiere: $tipo ('texto'|'lista'), $contenido, $items, $etiqueta --}}
<div class="caja-hoja" data-hoja data-tipo="{{ $tipo }}">
    @if ($tipo === 'lista')
        <ul class="caja-items" data-items aria-label="{{ $etiqueta }}">
            @foreach ($items as $item)
                @include('agenda._item', ['item' => $item])
            @endforeach
        </ul>
        <button type="button" class="caja-agregar" data-accion="agregar-item"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar ítem</button>
    @else
        <textarea class="caja-texto" data-campo="contenido" aria-label="{{ $etiqueta }}" placeholder="Escribí acá…">{{ $contenido }}</textarea>
    @endif
</div>
