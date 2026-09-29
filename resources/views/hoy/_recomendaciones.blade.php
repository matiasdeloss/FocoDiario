{{-- Recomendaciones para ahora, en acordeón. Requiere: $recomendaciones (colección de App\Services\Recomendaciones\Recomendacion). --}}
<section class="hoy-tarjeta hoy-recs" aria-labelledby="recomendaciones-titulo">
    <div class="hoy-tarjeta-cab">
        <h2 class="hoy-tarjeta-titulo" id="recomendaciones-titulo"><i class="bi bi-lightbulb hoy-titulo-icono" aria-hidden="true"></i>Recomendaciones para ahora</h2>
        <a href="{{ route('recomendaciones.index') }}" class="hoy-enlace">ver todas</a>
    </div>

    <div class="hoy-acordeon" data-acordeon>
        @forelse ($recomendaciones as $i => $recomendacion)
            @php $abierta = $i === 1; @endphp
            <article class="hoy-rec hoy-tono-{{ $recomendacion->tipo->value }} {{ $abierta ? 'es-abierta' : '' }}">
                <div class="hoy-rec-cabecera">
                    <span class="hoy-rec-icono" aria-hidden="true"><i class="bi {{ $recomendacion->icono }}"></i></span>
                    <div class="hoy-rec-contenido">
                        <h3 class="hoy-rec-titulo">
                            <button type="button" class="hoy-rec-boton" aria-expanded="{{ $abierta ? 'true' : 'false' }}" aria-controls="hoy-rec-{{ $i }}">
                                <span class="hoy-rec-nombre">{{ $recomendacion->titulo }}</span>
                                <span class="hoy-etiqueta">{{ mb_strtoupper($recomendacion->tipo->etiqueta()) }}</span>
                                <i class="bi bi-plus hoy-rec-mas" aria-hidden="true"></i>
                                <i class="bi bi-dash hoy-rec-menos" aria-hidden="true"></i>
                            </button>
                        </h3>
                        <p class="hoy-rec-texto">{{ $recomendacion->mensaje }}</p>
                    </div>
                </div>
                <div class="hoy-rec-detalle" id="hoy-rec-{{ $i }}" role="region" aria-label="Detalle: {{ $recomendacion->titulo }}" @unless ($abierta) hidden @endunless>
                    @foreach ($recomendacion->datos as $etiqueta => $valor)
                        <div class="hoy-dato">
                            <span class="hoy-dato-etiqueta">{{ $etiqueta }}</span>
                            <span class="hoy-dato-valor">{{ $valor }}</span>
                        </div>
                    @endforeach
                    @if ($recomendacion->fuente)
                        <p class="hoy-rec-fuente">
                            Fuente:
                            @if ($recomendacion->fuente->url)
                                <a href="{{ $recomendacion->fuente->url }}" target="_blank" rel="noopener noreferrer">{{ $recomendacion->fuente->texto }}</a>
                            @else
                                {{ $recomendacion->fuente->texto }}
                            @endif
                        </p>
                    @endif
                </div>
            </article>
        @empty
            <p class="hoy-vacio">Por ahora no hay recomendaciones.</p>
        @endforelse
    </div>
</section>
