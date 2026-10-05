{{-- Opciones de un selector de contextos, agrupadas por tipo. Requiere: $grupos ([etiqueta del tipo => [id => ruta]]). Opcional: $elegido.
     Con $conColor, cada opción lleva su color efectivo (data-color-heredado y data-color-origen) para la pista de color de los diálogos;
     $colores (App\Support\ColoresDeContexto) evita una consulta extra. --}}
@php($coloresCtx = ($conColor ?? false) ? ($colores ?? \App\Support\ColoresDeContexto::delUsuario()) : null)
@foreach ($grupos as $tipo => $opciones)
    <optgroup label="{{ $tipo }}">
        @foreach ($opciones as $id => $ruta)
            @php($heredado = $coloresCtx?->visible($id))
            <option value="{{ $id }}" @selected((string) ($elegido ?? '') === (string) $id) @if ($heredado) data-color-heredado="{{ $heredado->color->clave() }}" data-color-origen="{{ $heredado->origen }}" @endif>{{ $ruta }}</option>
        @endforeach
    </optgroup>
@endforeach
