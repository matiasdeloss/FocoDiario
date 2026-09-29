<tr id="recordatorio-{{ $recordatorio->id }}">
    <td>
        <div class="{{ $recordatorio->avisado_en ? 'texto-tachado' : 'fw-medium' }}">{{ $recordatorio->mensaje }}</div>
        @if ($recordatorio->descripcion)
            <div class="small text-secondary">{{ \Illuminate\Support\Str::limit($recordatorio->descripcion, 90) }}</div>
        @endif
        @if ($recordatorio->tarea)
            <div class="small text-secondary"><i class="bi bi-link-45deg"></i> {{ $recordatorio->tarea->titulo }}</div>
        @endif
    </td>
    <td class="text-nowrap">
        @if ($recordatorio->recordar_en)
            {{ $recordatorio->recordar_en->format('d/m/Y H:i') }}
        @else
            <span class="text-secondary">Sin fecha</span>
        @endif
    </td>
    <td>
        @if ($recordatorio->avisado_en)
            <span class="badge-foco badge-estado-completada">Avisado</span>
        @elseif ($recordatorio->recordar_en === null)
            <span class="badge-foco badge-estado-pendiente">Por ubicar</span>
        @elseif ($recordatorio->recordar_en->isPast())
            <span class="badge-foco badge-estado-vencida">Atrasado</span>
        @else
            <span class="badge-foco badge-estado-pendiente">Pendiente</span>
        @endif
    </td>
    <td class="text-end text-nowrap">
        @if ($recordatorio->avisado_en)
            <form method="POST" action="{{ route('recordatorios.reactivar', $recordatorio) }}" class="d-inline"
                  hx-patch="{{ route('recordatorios.reactivar', $recordatorio) }}" hx-target="closest tr" hx-swap="outerHTML">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn-icono" title="Volver a pendiente" aria-label="Volver a pendiente: {{ $recordatorio->mensaje }}">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('recordatorios.avisar', $recordatorio) }}" class="d-inline"
                  hx-patch="{{ route('recordatorios.avisar', $recordatorio) }}" hx-target="closest tr" hx-swap="outerHTML">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn-icono" title="Marcar como avisado" aria-label="Marcar como avisado: {{ $recordatorio->mensaje }}">
                    <i class="bi bi-check2-circle"></i>
                </button>
            </form>
        @endif
        <a href="{{ route('recordatorios.edit', $recordatorio) }}" class="btn-icono" title="Editar" aria-label="Editar {{ $recordatorio->mensaje }}">
            <i class="bi bi-pencil"></i>
        </a>
        <form method="POST" action="{{ route('recordatorios.destroy', $recordatorio) }}" class="d-inline"
              hx-delete="{{ route('recordatorios.destroy', $recordatorio) }}" hx-target="closest tr" hx-swap="outerHTML"
              hx-confirm="¿Eliminar este recordatorio?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar {{ $recordatorio->mensaje }}">
                <i class="bi bi-trash"></i>
            </button>
        </form>
    </td>
</tr>
