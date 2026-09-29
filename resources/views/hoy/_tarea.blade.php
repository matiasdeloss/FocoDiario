{{-- Fila de una tarea en Hoy. Requiere: $tarea. Se usa también para la tarea recién creada (respuesta JSON del alta rápida). --}}
@php $hecha = $tarea->estado === \App\Enums\EstadoTarea::Completada; @endphp
<li class="hoy-item {{ $hecha ? 'es-hecha' : '' }}" data-tarea="{{ $tarea->id }}">
    <button type="button" class="hoy-fila" role="checkbox" aria-checked="{{ $hecha ? 'true' : 'false' }}"
            data-url="{{ route('tareas.estado', $tarea) }}">
        <span class="hoy-check" aria-hidden="true">@include('hoy._icono-check')</span>
        <span class="hoy-fila-texto"><span class="hoy-fila-titulo">{{ $tarea->titulo }}</span></span>
        <span class="hoy-prio hoy-prio-{{ $tarea->prioridad->value }}" aria-hidden="true"></span>
        <span class="hoy-solo-lector">Prioridad {{ mb_strtolower($tarea->prioridad->etiqueta()) }}</span>
    </button>
</li>
