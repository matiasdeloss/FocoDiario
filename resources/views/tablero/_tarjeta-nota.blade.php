{{-- Tarjeta de una nota en el tablero. Requiere: $nota (con columna y contexto), $columnasOrden. Opcional: $colores (ColoresDeContexto).
     Las notas también son tarjetas: se mueven por las columnas como las tareas y se abren en el diálogo de la nota. --}}
@php
    $ids = $columnasOrden->pluck('id')->all();
    $posicion = array_search($nota->columna_id, $ids, true);
    $anterior = $posicion === false ? null : ($columnasOrden[$posicion - 1] ?? null);
    $siguiente = $posicion === false ? null : ($columnasOrden[$posicion + 1] ?? null);
    $colorVisible = $nota->colorVisible($colores ?? null);
    $titulo = $nota->tituloVisible();
    $completada = $columnasOrden->firstWhere('id', $nota->columna_id)?->esCompletada() ?? false;
    $extracto = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', (string) $nota->contenido)), 120);
@endphp
<article class="tarea-tarjeta tarjeta-nota @if ($colorVisible) con-color @endif" id="nota-tablero-{{ $nota->id }}" draggable="true"
         @if ($colorVisible) style="--tarjeta-fondo: {{ $colorVisible->fondo() }}; --tarjeta-marca: {{ $colorVisible->marca() }}" @endif
         data-id="{{ $nota->id }}" data-tipo="nota" data-estado="{{ $completada ? 'completada' : 'abierta' }}" data-titulo="{{ $titulo }}"
         data-prioridad="" data-contexto="{{ $nota->contexto_id }}"
         data-buscar="{{ mb_strtolower($titulo.' '.$extracto.' '.$nota->contexto?->nombre) }}">
    <div class="tarea-titulo {{ $completada ? 'texto-tachado' : 'fw-medium' }}" data-titulo-texto>{{ $titulo }}</div>
    @if ($extracto !== '' && $extracto !== $titulo)
        <p class="tarea-comentario" title="Contenido"><i class="bi bi-chat-left-text" aria-hidden="true"></i><span>{{ $extracto }}</span></p>
    @endif
    <div class="tarea-meta">
        <span class="k-tipo k-tipo-nota" title="Nota"><i class="bi bi-journal-text" aria-hidden="true"></i>Nota</span>
        @if ($nota->contexto)
            <span class="k-chip k-chip-contexto" title="{{ $nota->contexto->tipo->etiqueta() }}"><i class="bi bi-folder2" aria-hidden="true"></i><span class="k-chip-texto">{{ $nota->contexto->nombre }}</span></span>
        @endif
        @if ($nota->fecha)
            <span class="k-chip k-chip-fecha" title="Fecha de la nota"><i class="bi bi-calendar-event" aria-hidden="true"></i>{{ $nota->fecha->locale('es')->isoFormat('D MMM') }}</span>
        @endif
    </div>
    <div class="tarea-acciones">
        <form method="POST" action="{{ route('notas.columna', $nota) }}" class="d-inline-flex" data-mover>
            @csrf
            @method('PATCH')
            <button type="submit" name="columna_id" value="{{ $anterior?->id }}" class="btn-icono" data-mover-a="anterior"
                    aria-label="Mover {{ $titulo }} a {{ $anterior?->nombre }}" title="Mover a {{ $anterior?->nombre }}"
                    @disabled($anterior === null)><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
            <button type="submit" name="columna_id" value="{{ $siguiente?->id }}" class="btn-icono" data-mover-a="siguiente"
                    aria-label="Mover {{ $titulo }} a {{ $siguiente?->nombre }}" title="Mover a {{ $siguiente?->nombre }}"
                    @disabled($siguiente === null)><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        </form>
        <a href="{{ route('notas.edit', $nota) }}" class="btn-icono" data-abrir-nota="editar" data-nota="{{ json_encode($nota->datosEdicion(), JSON_UNESCAPED_UNICODE) }}" title="Editar" aria-label="Editar {{ $titulo }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
        <form method="POST" action="{{ route('notas.destroy', $nota) }}" class="d-inline"
              hx-delete="{{ route('notas.destroy', $nota) }}" hx-target="closest .tarea-tarjeta" hx-swap="outerHTML"
              data-deshacer="Nota eliminada.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar {{ $titulo }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
        </form>
    </div>
</article>
