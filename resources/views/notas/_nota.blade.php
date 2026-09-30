{{-- Tarjeta de una nota. Requiere: $nota, $destinos --}}
@php
    $datosEdicion = [
        'url' => route('notas.update', $nota),
        'titulo' => $nota->titulo,
        'contenido' => $nota->contenido,
        'contexto_id' => $nota->contexto_id,
        'fecha' => $nota->fecha?->format('Y-m-d'),
        'color' => $nota->color?->value,
        'fijada' => $nota->fijada,
    ];
@endphp
<article id="nota-{{ $nota->id }}" class="nota-item {{ $nota->fijada ? 'nota-fijada' : '' }} {{ $nota->color ? 'nota-con-color' : '' }}"
         @if ($nota->color) style="--nota-fondo: {{ $nota->color->fondo() }}; --nota-marca: {{ $nota->color->marca() }}" @endif>
    <div class="nota-acciones">
        <form method="POST" action="{{ route('notas.fijar', $nota) }}"
              hx-patch="{{ route('notas.fijar', $nota) }}" hx-target="#nota-{{ $nota->id }}" hx-swap="outerHTML">
            @csrf
            @method('PATCH')
            <button type="submit" class="nota-boton {{ $nota->fijada ? 'activo' : '' }}"
                    title="{{ $nota->fijada ? 'Desfijar' : 'Fijar' }}" aria-label="{{ $nota->fijada ? 'Desfijar' : 'Fijar' }} nota"
                    aria-pressed="{{ $nota->fijada ? 'true' : 'false' }}">
                <i class="bi {{ $nota->fijada ? 'bi-pin-angle-fill' : 'bi-pin-angle' }}" aria-hidden="true"></i>
            </button>
        </form>
        <a href="{{ route('notas.edit', $nota) }}" class="nota-boton" title="Editar" aria-label="Editar nota"
           data-abrir-nota="editar" data-nota="{{ json_encode($datosEdicion, JSON_UNESCAPED_UNICODE) }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
        <form method="POST" action="{{ route('notas.destroy', $nota) }}"
              hx-delete="{{ route('notas.destroy', $nota) }}" hx-target="#nota-{{ $nota->id }}" hx-swap="outerHTML"
              data-deshacer="Nota eliminada.">
            @csrf
            @method('DELETE')
            <button type="submit" class="nota-boton nota-boton-peligro" title="Eliminar" aria-label="Eliminar nota"><i class="bi bi-trash" aria-hidden="true"></i></button>
        </form>
    </div>

    @if ($nota->titulo)
        <h2 class="nota-titulo">{{ $nota->titulo }}</h2>
    @endif
    @if (trim((string) $nota->contenido) !== '')
        <div class="nota-contenido">{{ $nota->contenido }}</div>
    @elseif (! $nota->titulo)
        <p class="nota-vacia">Nota sin contenido</p>
    @endif

    <div class="nota-pie">
        {{-- La materia es una pastilla y a la vez el selector para mover la nota de destino. --}}
        <form method="POST" action="{{ route('notas.mover', $nota) }}" class="nota-mover"
              hx-patch="{{ route('notas.mover', $nota) }}" hx-trigger="change" hx-target="#nota-{{ $nota->id }}" hx-swap="outerHTML">
            @csrf
            @method('PATCH')
            <label for="mover-{{ $nota->id }}" class="visually-hidden">Mover nota a otro destino</label>
            <i class="bi {{ $nota->contexto ? 'bi-folder2' : 'bi-inbox' }}" aria-hidden="true"></i>
            <select id="mover-{{ $nota->id }}" name="contexto_id" title="Mover a otro destino">
                <option value="">Bandeja de entrada</option>
                @foreach ($destinos as $id => $ruta)
                    <option value="{{ $id }}" @selected($nota->contexto_id === $id)>{{ $ruta }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="nota-boton">Mover</button></noscript>
        </form>
        @if ($nota->fijada)
            <span class="nota-chip"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i> Fijada</span>
        @endif
        <span class="nota-fecha">
            @if ($nota->fecha)
                <i class="bi bi-calendar-event" aria-hidden="true"></i> {{ $nota->fecha->format('d/m/Y') }}
            @else
                {{ $nota->created_at->format('d/m/Y') }}
            @endif
        </span>
    </div>
</article>
