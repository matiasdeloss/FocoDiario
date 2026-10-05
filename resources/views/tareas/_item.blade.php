{{-- Fila de la lista unificada (una tarea o un recordatorio). Requiere: $item (App\Services\Tareas\ItemLista), $hoy.
     También es la respuesta HTMX de los cambios de estado. --}}
@php
    $tipo = $item->tipo;
    $modelo = $item->modelo;
    $esTarea = $tipo === 'tarea';
    $hecha = $item->hecha;
    $grupo = $item->grupo($hoy);
    $vencida = ! $hecha && $grupo === 'vencidas';
    $etiquetaFecha = $item->etiquetaFecha($hoy);
    $urlHecho = $esTarea ? route('tareas.estado', $modelo) : route('recordatorios.avisar', $modelo);
    $urlDeshacer = $esTarea ? route('tareas.estado', $modelo) : route('recordatorios.reactivar', $modelo);
@endphp
<li id="{{ $tipo }}-{{ $modelo->id }}" class="t-fila tipo-{{ $tipo }} {{ $hecha ? 'es-hecha' : '' }}"
    data-tipo="{{ $tipo }}" data-id="{{ $modelo->id }}" data-momento="{{ $grupo }}" data-orden="{{ $item->cuando?->getTimestamp() }}"
    data-buscar="{{ mb_strtolower($item->titulo.' '.$item->contexto.' '.$item->detalle) }}">
    <form method="POST" action="{{ $hecha ? $urlDeshacer : $urlHecho }}" class="t-check-form">
        @csrf
        @method('PATCH')
        @if ($esTarea)
            <input type="hidden" name="estado" value="{{ $hecha ? 'pendiente' : 'completada' }}">
        @endif
        <button type="submit" class="t-check" role="checkbox" aria-checked="{{ $hecha ? 'true' : 'false' }}" aria-label="{{ $item->titulo }}"
                data-url-hecho="{{ $urlHecho }}" data-url-deshacer="{{ $urlDeshacer }}">
            <span class="t-check-caja" aria-hidden="true">@include('hoy._icono-check')</span>
        </button>
    </form>

    <div class="t-cuerpo">
        <div class="t-titulo">{{ $item->titulo }}</div>
        @if ($item->detalle)
            <div class="t-detalle">@if (! $esTarea && $modelo->tarea)<i class="bi bi-link-45deg" aria-hidden="true"></i> @endif{{ $item->detalle }}</div>
        @endif
    </div>

    <div class="t-meta">
        <span class="t-tipo"><span class="t-punto" aria-hidden="true"></span>{{ $esTarea ? 'Tarea' : 'Recordatorio' }}</span>
        @if ($esTarea)
            <span class="t-contexto" title="Prioridad {{ mb_strtolower($modelo->prioridad->etiqueta()) }}">
                <span class="t-prio t-prio-{{ $item->prioridad }}" aria-hidden="true"></span>
                <span class="visually-hidden">Prioridad {{ mb_strtolower($modelo->prioridad->etiqueta()) }}.</span>
                @if ($item->contexto)<span class="t-contexto-nombre">{{ $item->contexto }}</span>@endif
            </span>
        @endif
        @if ($etiquetaFecha !== '')
            <time class="t-fecha {{ $vencida ? 'es-vencida' : '' }}" title="{{ $item->fechaCompleta() }}" datetime="{{ $item->cuando->toIso8601String() }}">{{ $etiquetaFecha }}</time>
        @endif
    </div>

    <div class="t-acciones">
        @if ($esTarea)
            <a href="{{ route('tareas.edit', $modelo) }}" class="btn-icono" data-abrir-tarea="editar"
               data-tarea="{{ json_encode($modelo->datosModal(), JSON_UNESCAPED_UNICODE) }}" title="Editar" aria-label="Editar {{ $item->titulo }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
            <form method="POST" action="{{ route('tareas.destroy', $modelo) }}" class="d-inline"
                  hx-delete="{{ route('tareas.destroy', $modelo) }}" hx-target="closest li" hx-swap="outerHTML"
                  data-deshacer="Tarea eliminada.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar {{ $item->titulo }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
            </form>
        @else
            <a href="{{ route('recordatorios.edit', $modelo) }}" class="btn-icono" data-abrir-recordatorio="editar"
               data-recordatorio="{{ json_encode($modelo->datosModal(), JSON_UNESCAPED_UNICODE) }}" title="Editar" aria-label="Editar {{ $item->titulo }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
            <form method="POST" action="{{ route('recordatorios.destroy', $modelo) }}" class="d-inline"
                  hx-delete="{{ route('recordatorios.destroy', $modelo) }}" hx-target="closest li" hx-swap="outerHTML"
                  data-deshacer="Recordatorio eliminado.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar {{ $item->titulo }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
            </form>
        @endif
    </div>
</li>
