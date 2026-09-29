{{-- Tarjeta de una nota. Requiere: $nota, $destinos --}}
<article id="nota-{{ $nota->id }}" class="nota-item {{ $nota->fijada ? 'nota-fijada' : '' }}">
    @if ($nota->titulo)
        <div class="fw-semibold">{{ $nota->titulo }}</div>
    @endif
    <div class="nota-contenido">{{ $nota->contenido }}</div>
    <div class="nota-meta">
        @if ($nota->fijada)
            <span class="badge-foco badge-estado-pendiente"><i class="bi bi-pin-angle-fill"></i> Fijada</span>
        @endif
        @if ($nota->color)
            {{-- Marca de color de la nota (paleta Organic). El estilo va en línea para no depender de app.css. --}}
            <span class="text-secondary small text-nowrap" title="Color: {{ mb_strtolower($nota->color->etiqueta()) }}">
                <span aria-hidden="true" style="display:inline-block;width:.7em;height:.7em;border-radius:50%;background:{{ $nota->color->marca() }};box-shadow:0 0 0 3px {{ $nota->color->fondo() }}"></span>
                {{ $nota->color->etiqueta() }}
            </span>
        @endif
        <span class="text-secondary small">
            @if ($nota->contexto)
                <i class="bi bi-folder2"></i> {{ $nota->contexto->rutaCompleta() }}
            @else
                <i class="bi bi-inbox"></i> Bandeja de entrada
            @endif
        </span>
        @if ($nota->fecha)
            <span class="text-secondary small text-nowrap"><i class="bi bi-calendar-event"></i> {{ $nota->fecha->format('d/m/Y') }}</span>
        @endif
        <span class="text-secondary small text-nowrap">{{ $nota->created_at->format('d/m/Y H:i') }}</span>
    </div>
    <div class="nota-acciones">
        <form method="POST" action="{{ route('notas.mover', $nota) }}" class="nota-mover"
              hx-patch="{{ route('notas.mover', $nota) }}" hx-trigger="change" hx-target="#nota-{{ $nota->id }}" hx-swap="outerHTML">
            @csrf
            @method('PATCH')
            <label for="mover-{{ $nota->id }}" class="visually-hidden">Mover nota a otro destino</label>
            <select id="mover-{{ $nota->id }}" name="contexto_id" class="form-select form-select-sm">
                <option value="">Bandeja de entrada</option>
                @foreach ($destinos as $id => $ruta)
                    <option value="{{ $id }}" @selected($nota->contexto_id === $id)>{{ $ruta }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn btn-foco-suave btn-sm">Mover</button></noscript>
        </form>
        <form method="POST" action="{{ route('notas.fijar', $nota) }}" class="d-inline"
              hx-patch="{{ route('notas.fijar', $nota) }}" hx-target="#nota-{{ $nota->id }}" hx-swap="outerHTML">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn-icono {{ $nota->fijada ? 'activo' : '' }}"
                    title="{{ $nota->fijada ? 'Desfijar' : 'Fijar' }}" aria-label="{{ $nota->fijada ? 'Desfijar' : 'Fijar' }} nota"
                    aria-pressed="{{ $nota->fijada ? 'true' : 'false' }}">
                <i class="bi {{ $nota->fijada ? 'bi-pin-angle-fill' : 'bi-pin-angle' }}"></i>
            </button>
        </form>
        <a href="{{ route('notas.edit', $nota) }}" class="btn-icono" title="Editar" aria-label="Editar nota"><i class="bi bi-pencil"></i></a>
        <form method="POST" action="{{ route('notas.destroy', $nota) }}" class="d-inline"
              hx-delete="{{ route('notas.destroy', $nota) }}" hx-target="#nota-{{ $nota->id }}" hx-swap="outerHTML"
              hx-confirm="¿Eliminar esta nota?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar nota"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</article>
