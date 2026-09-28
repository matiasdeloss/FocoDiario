{{-- Tarea sin fecha del panel del calendario. Requiere: $tarea. Se arrastra sobre un día; el campo de fecha es la alternativa por teclado. --}}
<li class="tarea-arrastrable" data-tarea-id="{{ $tarea->id }}" data-titulo="{{ $tarea->titulo }}"
    data-prioridad="{{ $tarea->prioridad->value }}" data-prioridad-etiqueta="{{ $tarea->prioridad->etiqueta() }}"
    data-url="{{ route('tareas.edit', $tarea) }}">
    <div class="tarea-arrastrable-cabeza">
        <i class="bi bi-grip-vertical tarea-arrastrable-asa" aria-hidden="true"></i>
        <a href="{{ route('tareas.edit', $tarea) }}" class="tarea-arrastrable-titulo">{{ $tarea->titulo }}</a>
        <span class="badge-foco {{ $tarea->prioridad->claseBadge() }}">{{ $tarea->prioridad->etiqueta() }}</span>
    </div>
    <input type="date" class="form-control form-control-sm tarea-arrastrable-fecha" data-fecha-tarea
           aria-label="Asignar fecha límite a {{ $tarea->titulo }}">
</li>
