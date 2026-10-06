{{-- Tarjeta de una nota. Requiere: $nota, $destinos. Opcional: $colores (ColoresDeContexto, para listar sin una consulta por nota). --}}
@php
    // Color propio o, si no tiene, el del contexto (o su ancestro). El selector de edición sigue mostrando solo el propio.
    $colorVisible = $nota->colorVisible($colores ?? null);
    $datosEdicion = $nota->datosEdicion();
    // Una nota completada es la que está en una columna de ese tipo (se ve atenuada y con su marca).
    $completada = $nota->estaCompletada();
    // Lo que muestra el modal de lectura (notas.js): la nota completa, sin recortar.
    $datosLectura = [
        'titulo' => $nota->titulo,
        'contenido' => $nota->contenido,
        'destino' => $nota->contexto_id ? ($destinos[$nota->contexto_id] ?? $nota->contexto?->nombre) : null,
        'fecha' => $nota->fecha?->translatedFormat('j \d\e F \d\e Y'),
        'creada' => $nota->created_at?->translatedFormat('j \d\e F \d\e Y, H:i'),
        'editada' => $nota->updated_at && $nota->updated_at->ne($nota->created_at) ? $nota->updated_at->translatedFormat('j \d\e F \d\e Y, H:i') : null,
        'fijada' => $nota->fijada,
        'fondo' => $colorVisible?->fondo(),
        'marca' => $colorVisible?->marca(),
    ];
@endphp
<article id="nota-{{ $nota->id }}" class="nota-item {{ $nota->fijada ? 'nota-fijada' : '' }} {{ $colorVisible ? 'nota-con-color' : '' }} {{ $completada ? 'nota-completada' : '' }}"
         @if ($colorVisible) style="--nota-fondo: {{ $colorVisible->fondo() }}; --nota-marca: {{ $colorVisible->marca() }}" @endif>
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
        {{-- Ocultar/mostrar: solo afecta a esta pantalla. notas.js lo envía por fetch y ofrece "Deshacer"; sin JS es un formulario normal. --}}
        <form method="POST" action="{{ route($nota->oculta ? 'notas.mostrar' : 'notas.ocultar', $nota) }}"
              data-alternar-oculta="{{ $nota->oculta ? 'mostrar' : 'ocultar' }}"
              data-url-inversa="{{ route($nota->oculta ? 'notas.ocultar' : 'notas.mostrar', $nota) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="nota-boton" title="{{ $nota->oculta ? 'Mostrar' : 'Ocultar' }}" aria-label="{{ $nota->oculta ? 'Mostrar' : 'Ocultar' }} nota">
                <i class="bi {{ $nota->oculta ? 'bi-eye' : 'bi-eye-slash' }}" aria-hidden="true"></i>
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

    @if ($completada)
        <span class="nota-marca-completada"><i class="bi bi-check2-circle" aria-hidden="true"></i> Completada</span>
    @endif

    {{-- Título y contenido abren la nota completa (modal de lectura); sin JS, la página de la nota. --}}
    <a href="{{ route('notas.show', $nota) }}" class="nota-abrir" data-ver-nota="{{ json_encode($datosLectura, JSON_UNESCAPED_UNICODE) }}"
       aria-label="Ver nota: {{ $nota->tituloVisible() ?: 'sin contenido' }}">
        @if ($nota->titulo)
            <h2 class="nota-titulo">{{ $nota->titulo }}</h2>
        @endif
        @if (trim((string) $nota->contenido) !== '')
            <div class="nota-contenido">{{ $nota->contenido }}</div>
        @elseif (! $nota->titulo)
            <p class="nota-vacia">Nota sin contenido</p>
        @endif
    </a>

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
