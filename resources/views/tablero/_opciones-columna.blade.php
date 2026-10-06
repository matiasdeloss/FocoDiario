{{-- Opciones de un selector de columnas, agrupadas por tablero (el nombre del tablero es el grupo).
     Requiere: $columnas (ColumnaTablero::paraSelector(), con su tablero). Opcional: $elegida (id). --}}
@foreach ($columnas->groupBy('tablero_id') as $delTablero)
    <optgroup label="{{ $delTablero->first()->tablero?->nombre }}{{ $delTablero->first()->tablero?->principal ? ' (principal)' : '' }}">
        @foreach ($delTablero as $columna)
            <option value="{{ $columna->id }}" data-tablero="{{ $columna->tablero_id }}" @selected((string) ($elegida ?? '') === (string) $columna->id)>{{ $columna->nombre }}</option>
        @endforeach
    </optgroup>
@endforeach
