<tr id="tarea-{{ $tarea->id }}">
    <td>
        <div class="{{ $tarea->estado === \App\Enums\EstadoTarea::Completada ? 'texto-tachado' : 'fw-medium' }}">{{ $tarea->titulo }}</div>
        @if ($tarea->proyecto)
            <div class="small text-secondary">{{ $tarea->proyecto }}</div>
        @endif
    </td>
    <td class="text-nowrap">
        @if ($tarea->fecha_limite)
            <span class="{{ $tarea->estaVencida() ? 'texto-vencida' : '' }}">{{ $tarea->fecha_limite->format('d/m/Y') }}</span>
            @if ($tarea->estaVencida())
                <span class="badge-foco badge-estado-vencida ms-1">Vencida</span>
            @endif
        @else
            <span class="text-secondary">Sin fecha</span>
        @endif
    </td>
    <td><span class="badge-foco {{ $tarea->prioridad->claseBadge() }}">{{ $tarea->prioridad->etiqueta() }}</span></td>
    <td><span class="badge-foco {{ $tarea->estado->claseBadge() }}">{{ $tarea->estado->etiqueta() }}</span></td>
    <td>
        <form method="POST" action="{{ route('tareas.estado', $tarea) }}" class="d-inline-flex"
              hx-patch="{{ route('tareas.estado', $tarea) }}" hx-target="closest tr" hx-swap="outerHTML"
              role="group" aria-label="Cambiar estado de {{ $tarea->titulo }}">
            @csrf
            @method('PATCH')
            @foreach (\App\Enums\EstadoTarea::cases() as $estado)
                <button type="submit" name="estado" value="{{ $estado->value }}"
                        class="btn-icono {{ $tarea->estado === $estado ? 'activo' : '' }}"
                        title="Marcar como {{ mb_strtolower($estado->etiqueta()) }}"
                        aria-label="Marcar como {{ mb_strtolower($estado->etiqueta()) }}"
                        aria-pressed="{{ $tarea->estado === $estado ? 'true' : 'false' }}">
                    <i class="bi {{ $estado->icono() }}"></i>
                </button>
            @endforeach
        </form>
    </td>
    <td class="text-end text-nowrap">
        <a href="{{ route('tareas.edit', $tarea) }}" class="btn-icono" title="Editar" aria-label="Editar {{ $tarea->titulo }}">
            <i class="bi bi-pencil"></i>
        </a>
        <form method="POST" action="{{ route('tareas.destroy', $tarea) }}" class="d-inline"
              hx-delete="{{ route('tareas.destroy', $tarea) }}" hx-target="closest tr" hx-swap="outerHTML"
              hx-confirm="¿Eliminar la tarea &quot;{{ $tarea->titulo }}&quot;?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar {{ $tarea->titulo }}">
                <i class="bi bi-trash"></i>
            </button>
        </form>
    </td>
</tr>
