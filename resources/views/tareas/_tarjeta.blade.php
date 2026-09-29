@php
    $ids = $columnasOrden->pluck('id')->all();
    $posicion = array_search($tarea->columna_id, $ids, true);
    $anterior = $posicion === false ? null : ($columnasOrden[$posicion - 1] ?? null);
    $siguiente = $posicion === false ? null : ($columnasOrden[$posicion + 1] ?? null);
    $pasada = $tarea->fecha_limite !== null && $tarea->fecha_limite->lt(today());
    $completada = $tarea->estado === \App\Enums\EstadoTarea::Completada;
@endphp
<article class="tarea-tarjeta prioridad-{{ $tarea->prioridad->value }}" id="tarea-{{ $tarea->id }}" draggable="true"
         data-id="{{ $tarea->id }}" data-estado="{{ $tarea->estado->value }}" data-titulo="{{ $tarea->titulo }}"
         title="Prioridad {{ $tarea->prioridad->etiqueta() }}"
         @if ($pasada) data-pasada="1" @endif>
    <div class="tarea-titulo {{ $completada ? 'texto-tachado' : 'fw-medium' }}" data-titulo-texto>{{ $tarea->titulo }}</div>
    @if ($tarea->proyecto || $tarea->fecha_limite)
        <div class="tarea-meta">
            @if ($tarea->proyecto)
                <span class="text-secondary"><i class="bi bi-folder2"></i> {{ $tarea->proyecto }}</span>
            @endif
            @if ($tarea->fecha_limite)
                <span class="tarea-fecha {{ $pasada ? 'fecha-pasada' : '' }}"><i class="bi bi-calendar-event"></i> {{ $tarea->fecha_limite->format('d/m') }}</span>
            @endif
        </div>
    @endif
    <div class="tarea-acciones">
        <form method="POST" action="{{ route('tareas.columna', $tarea) }}" class="d-inline-flex" data-mover>
            @csrf
            @method('PATCH')
            <button type="submit" name="columna_id" value="{{ $anterior?->id }}" class="btn-icono" data-mover-a="anterior"
                    aria-label="Mover {{ $tarea->titulo }} a {{ $anterior?->nombre }}" title="Mover a {{ $anterior?->nombre }}"
                    @disabled($anterior === null)><i class="bi bi-arrow-left"></i></button>
            <button type="submit" name="columna_id" value="{{ $siguiente?->id }}" class="btn-icono" data-mover-a="siguiente"
                    aria-label="Mover {{ $tarea->titulo }} a {{ $siguiente?->nombre }}" title="Mover a {{ $siguiente?->nombre }}"
                    @disabled($siguiente === null)><i class="bi bi-arrow-right"></i></button>
        </form>
        <a href="{{ route('tareas.edit', $tarea) }}" class="btn-icono" data-abrir-tarea="editar" data-tarea="{{ json_encode($tarea->datosModal(), JSON_UNESCAPED_UNICODE) }}" title="Editar" aria-label="Editar {{ $tarea->titulo }}"><i class="bi bi-pencil"></i></a>
        <form method="POST" action="{{ route('tareas.destroy', $tarea) }}" class="d-inline"
              hx-delete="{{ route('tareas.destroy', $tarea) }}" hx-target="closest .tarea-tarjeta" hx-swap="outerHTML"
              hx-confirm="¿Eliminar la tarea &quot;{{ $tarea->titulo }}&quot;?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar {{ $tarea->titulo }}"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</article>
