{{-- Tarjeta compacta del tablero. Requiere: $tarea, $columnasOrden. También se devuelve al añadir una tarjeta desde el pie de la columna. --}}
@php
    $ids = $columnasOrden->pluck('id')->all();
    $posicion = array_search($tarea->columna_id, $ids, true);
    $anterior = $posicion === false ? null : ($columnasOrden[$posicion - 1] ?? null);
    $siguiente = $posicion === false ? null : ($columnasOrden[$posicion + 1] ?? null);
    $completada = $tarea->estado === \App\Enums\EstadoTarea::Completada;
    $hoyTarjeta = today();
    $estadoFecha = match (true) {
        $tarea->fecha_limite === null => null,
        $completada => 'normal',
        $tarea->fecha_limite->lt($hoyTarjeta) => 'vencida',
        $tarea->fecha_limite->eq($hoyTarjeta) => 'hoy',
        default => 'normal',
    };
    $textoFecha = $tarea->fecha_limite === null ? '' : match (true) {
        $tarea->fecha_limite->eq($hoyTarjeta) => 'Hoy',
        $tarea->fecha_limite->eq($hoyTarjeta->copy()->addDay()) => 'Mañana',
        default => rtrim($tarea->fecha_limite->locale('es')->isoFormat($tarea->fecha_limite->year === $hoyTarjeta->year ? 'D MMM' : 'D MMM YYYY'), '.'),
    };
@endphp
<article class="tarea-tarjeta prioridad-{{ $tarea->prioridad->value }}" id="tarea-{{ $tarea->id }}" draggable="true"
         data-id="{{ $tarea->id }}" data-estado="{{ $tarea->estado->value }}" data-titulo="{{ $tarea->titulo }}"
         data-prioridad="{{ $tarea->prioridad->value }}" data-proyecto="{{ $tarea->proyecto }}"
         data-buscar="{{ mb_strtolower($tarea->titulo.' '.$tarea->proyecto) }}"
         @if ($estadoFecha === 'vencida') data-pasada="1" @endif>
    <div class="tarea-titulo {{ $completada ? 'texto-tachado' : 'fw-medium' }}" data-titulo-texto>{{ $tarea->titulo }}</div>
    <div class="tarea-meta">
        <span class="k-chip badge-prioridad-{{ $tarea->prioridad->value }}" title="Prioridad {{ mb_strtolower($tarea->prioridad->etiqueta()) }}"><span class="k-chip-punto" aria-hidden="true"></span>{{ $tarea->prioridad->etiqueta() }}</span>
        @if ($tarea->proyecto)
            <span class="k-chip k-chip-proyecto"><i class="bi bi-folder2" aria-hidden="true"></i><span class="k-chip-texto">{{ $tarea->proyecto }}</span></span>
        @endif
        @if ($tarea->fecha_limite)
            <span class="k-chip k-chip-fecha k-fecha-{{ $estadoFecha }}" title="Fecha límite: {{ $tarea->fecha_limite->locale('es')->isoFormat('dddd D [de] MMMM') }}">
                <i class="bi bi-calendar-event" aria-hidden="true"></i>{{ $textoFecha }}
                @if ($estadoFecha === 'vencida')<span class="marca-vencida">· vencida</span>@endif
            </span>
        @endif
    </div>
    <div class="tarea-acciones">
        <form method="POST" action="{{ route('tareas.columna', $tarea) }}" class="d-inline-flex" data-mover>
            @csrf
            @method('PATCH')
            <button type="submit" name="columna_id" value="{{ $anterior?->id }}" class="btn-icono" data-mover-a="anterior"
                    aria-label="Mover {{ $tarea->titulo }} a {{ $anterior?->nombre }}" title="Mover a {{ $anterior?->nombre }}"
                    @disabled($anterior === null)><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
            <button type="submit" name="columna_id" value="{{ $siguiente?->id }}" class="btn-icono" data-mover-a="siguiente"
                    aria-label="Mover {{ $tarea->titulo }} a {{ $siguiente?->nombre }}" title="Mover a {{ $siguiente?->nombre }}"
                    @disabled($siguiente === null)><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        </form>
        <a href="{{ route('tareas.edit', $tarea) }}" class="btn-icono" data-abrir-tarea="editar" data-tarea="{{ json_encode($tarea->datosModal(), JSON_UNESCAPED_UNICODE) }}" title="Editar" aria-label="Editar {{ $tarea->titulo }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
        <form method="POST" action="{{ route('tareas.destroy', $tarea) }}" class="d-inline"
              hx-delete="{{ route('tareas.destroy', $tarea) }}" hx-target="closest .tarea-tarjeta" hx-swap="outerHTML"
              hx-confirm="¿Eliminar la tarea &quot;{{ $tarea->titulo }}&quot;?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar {{ $tarea->titulo }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
        </form>
    </div>
</article>
