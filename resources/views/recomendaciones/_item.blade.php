@php /** @var \App\Services\Recomendaciones\Recomendacion $recomendacion */ @endphp
<article class="recomendacion recomendacion-{{ $recomendacion->tipo->value }}">
    <span class="recomendacion-icono" aria-hidden="true"><i class="bi {{ $recomendacion->icono }}"></i></span>
    <div class="recomendacion-cuerpo">
        <h3 class="recomendacion-titulo">
            {{ $recomendacion->titulo }}
            <span class="badge-foco {{ $recomendacion->tipo->claseBadge() }}">{{ $recomendacion->tipo->etiqueta() }}</span>
        </h3>
        <p class="recomendacion-mensaje">{{ $recomendacion->mensaje }}</p>
        @foreach ($recomendacion->datos as $etiqueta => $valor)
            <div class="lista-fila">
                <span>{{ $etiqueta }}</span>
                <span class="lista-fila-meta">{{ $valor }}</span>
            </div>
        @endforeach
        @if ($mostrarFuente ?? false)
            <p class="recomendacion-fuente">
                @if ($recomendacion->fuente)
                    Fuente:
                    @if ($recomendacion->fuente->url)
                        <a href="{{ $recomendacion->fuente->url }}" target="_blank" rel="noopener noreferrer">{{ $recomendacion->fuente->texto }}</a>
                    @else
                        {{ $recomendacion->fuente->texto }}
                    @endif
                @else
                    Sugerencia sin fuente verificada.
                @endif
            </p>
        @endif
    </div>
</article>
