@php
    $orden = \App\Enums\EstadoTarea::cases();
    $posicion = array_search($tarea->estado, $orden, true);
    $anterior = $orden[$posicion - 1] ?? null;
    $siguiente = $orden[$posicion + 1] ?? null;
    $pasada = $tarea->fecha_limite !== null && $tarea->fecha_limite->lt(today());
@endphp
<article class="tarea-tarjeta prioridad-{{ $tarea->prioridad->value }}" id="tarea-{{ $tarea->id }}" draggable="true"
         data-id="{{ $tarea->id }}" data-estado="{{ $tarea->estado->value }}" data-titulo="{{ $tarea->titulo }}"
         @if ($pasada) data-pasada="1" @endif>
    <div class="tarea-titulo {{ $tarea->estado === \App\Enums\EstadoTarea::Completada ? 'texto-tachado' : 'fw-medium' }}" data-titulo-texto>{{ $tarea->titulo }}</div>
    <div class="tarea-meta">
        @if ($tarea->proyecto)
            <span class="text-secondary"><i class="bi bi-folder2"></i> {{ $tarea->proyecto }}</span>
        @endif
        @if ($tarea->fecha_limite)
            <span class="tarea-fecha {{ $pasada ? 'fecha-pasada' : '' }}"><i class="bi bi-calendar-event"></i> {{ $tarea->fecha_limite->format('d/m/Y') }}</span>
            @if ($pasada)
                <span class="badge-foco badge-estado-vencida marca-vencida">Vencida</span>
            @endif
        @endif
        <span class="badge-foco {{ $tarea->prioridad->claseBadge() }}">{{ $tarea->prioridad->etiqueta() }}</span>
    </div>
    <div class="tarea-acciones">
        <form method="POST" action="{{ route('tareas.estado', $tarea) }}" class="d-inline-flex" data-mover>
            @csrf
            @method('PATCH')
            <button type="submit" name="estado" value="{{ $anterior?->value }}" class="btn-icono" data-mover-a="anterior"
                    aria-label="Mover {{ $tarea->titulo }} a {{ $anterior?->etiqueta() }}" title="Mover a {{ $anterior?->etiqueta() }}"
                    @disabled($anterior === null)><i class="bi bi-arrow-left"></i></button>
            <button type="submit" name="estado" value="{{ $siguiente?->value }}" class="btn-icono" data-mover-a="siguiente"
                    aria-label="Mover {{ $tarea->titulo }} a {{ $siguiente?->etiqueta() }}" title="Mover a {{ $siguiente?->etiqueta() }}"
                    @disabled($siguiente === null)><i class="bi bi-arrow-right"></i></button>
        </form>
        <span class="tarea-acciones-fin">
            <a href="{{ route('tareas.edit', $tarea) }}" class="btn-icono" title="Editar" aria-label="Editar {{ $tarea->titulo }}"><i class="bi bi-pencil"></i></a>
            <form method="POST" action="{{ route('tareas.destroy', $tarea) }}" class="d-inline"
                  hx-delete="{{ route('tareas.destroy', $tarea) }}" hx-target="closest .tarea-tarjeta" hx-swap="outerHTML"
                  hx-confirm="¿Eliminar la tarea &quot;{{ $tarea->titulo }}&quot;?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar {{ $tarea->titulo }}"><i class="bi bi-trash"></i></button>
            </form>
        </span>
    </div>
</article>
