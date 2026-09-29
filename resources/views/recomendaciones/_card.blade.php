{{-- Card de recomendación. Requiere: $clase (tono), $icono, $pill, $titulo, $texto. Opcionales: $datos, $fuente (Fuente|null), $general (bool: muestra "consejo general" si no hay fuente), $ruta, $accion. --}}
<article class="rec-card {{ $clase }}">
    <div class="rec-card-cabecera">
        <span class="rec-icono" aria-hidden="true"><i class="bi {{ $icono }}"></i></span>
        <span class="rec-pill">{{ $pill }}</span>
    </div>
    <h3 class="rec-titulo">{{ $titulo }}</h3>
    <p class="rec-texto">{{ $texto }}</p>
    @foreach ($datos ?? [] as $etiqueta => $valor)
        <div class="rec-dato"><span>{{ $etiqueta }}</span><strong>{{ $valor }}</strong></div>
    @endforeach
    @if (($fuente ?? null) || ($general ?? false))
        <p class="rec-fuente">
            @if ($fuente ?? null)
                Fuente:
                @if ($fuente->url)
                    <a href="{{ $fuente->url }}" target="_blank" rel="noopener noreferrer">{{ $fuente->texto }}</a>
                @else
                    {{ $fuente->texto }}
                @endif
            @else
                Consejo general, sin fuente específica.
            @endif
        </p>
    @endif
    @if ($ruta ?? null)
        <a class="rec-accion" href="{{ route($ruta) }}">{{ $accion }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    @endif
</article>
