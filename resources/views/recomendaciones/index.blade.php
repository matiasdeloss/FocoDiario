@extends('layouts.app')

@section('titulo', 'Recomendaciones · FocoDiario')

@push('head')
    @vite(['resources/css/recomendaciones.css'])
@endpush

@section('contenido')
    @php
        $total = $segunTusDatos + $ideas;
        $iconosCategoria = [
            'estudio' => 'bi-mortarboard',
            'salud' => 'bi-heart-pulse',
            'productividad' => 'bi-lightning-charge',
            'bienestar' => 'bi-flower1',
        ];
    @endphp
    <div class="rec-pagina">
        <header class="rec-hero">
            <div class="rec-hero-texto">
                <h1 class="pagina-titulo">Recomendaciones</h1>
                <p class="rec-resumen">
                    {{ $total }} {{ $total === 1 ? 'recomendación' : 'recomendaciones' }} hoy:
                    {{ $segunTusDatos }} según tus datos y {{ $ideas }} ideas de estudio y vida.
                    @if ($alertas > 0)
                        <span class="rec-resumen-alerta">{{ $alertas }} {{ $alertas === 1 ? 'alerta' : 'alertas' }}.</span>
                    @endif
                </p>
            </div>
            <dl class="rec-stats">
                <div class="rec-stat rec-tono-alerta">
                    <dt><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ $alertas === 1 ? 'Alerta' : 'Alertas' }}</dt>
                    <dd data-stat="alertas">{{ $alertas }}</dd>
                </div>
                <div class="rec-stat rec-tono-info">
                    <dt><i class="bi bi-graph-up" aria-hidden="true"></i> Según tus datos</dt>
                    <dd data-stat="datos">{{ $segunTusDatos }}</dd>
                </div>
                <div class="rec-stat rec-tono-logro">
                    <dt><i class="bi bi-lightbulb" aria-hidden="true"></i> Ideas del día</dt>
                    <dd data-stat="ideas">{{ $ideas }}</dd>
                </div>
            </dl>
        </header>

        @if ($paraTi->isNotEmpty())
            <section class="rec-destacadas" aria-labelledby="rec-para-ti">
                <div class="rec-destacadas-cabecera">
                    <h2 class="rec-seccion-titulo" id="rec-para-ti">Para ti ahora <span class="rec-cuenta">{{ $paraTi->count() }}</span></h2>
                    <p class="rec-seccion-sub">Según tu registro de hoy, tus tareas y recordatorios.</p>
                </div>
                <div class="rec-destacadas-lista">
                    @foreach ($paraTi as $recomendacion)
                        @include('recomendaciones._card', [
                            'clase' => 'rec-destacada rec-tono-'.$recomendacion->tipo->value,
                            'icono' => $recomendacion->icono,
                            'pill' => $recomendacion->tipo->etiqueta(),
                            'titulo' => $recomendacion->titulo,
                            'texto' => $recomendacion->mensaje,
                            'datos' => $recomendacion->datos,
                            'fuente' => $recomendacion->fuente,
                        ])
                    @endforeach
                </div>
            </section>
        @endif

        <div class="rec-paneles" data-paneles="{{ $secciones->count() }}">
            @foreach ($secciones as $seccion)
                @php($categoria = $seccion['categoria'])
                <section class="rec-panel rec-cat-{{ $categoria->value }}" aria-labelledby="rec-cat-{{ $categoria->value }}">
                    <header class="rec-panel-cabecera">
                        <span class="rec-panel-icono" aria-hidden="true"><i class="bi {{ $iconosCategoria[$categoria->value] ?? 'bi-stars' }}"></i></span>
                        <h2 class="rec-panel-titulo" id="rec-cat-{{ $categoria->value }}">{{ $categoria->etiqueta() }}</h2>
                        <span class="rec-cuenta">{{ $seccion['personales']->count() + $seccion['ideas']->count() }}</span>
                    </header>
                    <div class="rec-panel-lista">
                        @foreach ($seccion['personales'] as $recomendacion)
                            @include('recomendaciones._card', [
                                'clase' => 'rec-tono-'.$recomendacion->tipo->value,
                                'icono' => $recomendacion->icono,
                                'pill' => $recomendacion->tipo->etiqueta(),
                                'titulo' => $recomendacion->titulo,
                                'texto' => $recomendacion->mensaje,
                                'datos' => $recomendacion->datos,
                                'fuente' => $recomendacion->fuente,
                            ])
                        @endforeach
                        @foreach ($seccion['ideas'] as $consejo)
                            @include('recomendaciones._card', [
                                'clase' => 'rec-cat-'.$consejo->categoria->value,
                                'icono' => $consejo->icono,
                                'pill' => $consejo->categoria->etiqueta(),
                                'titulo' => $consejo->titulo,
                                'texto' => $consejo->texto,
                                'fuente' => $consejo->fuente,
                                'general' => true,
                                'ruta' => $consejo->ruta,
                                'accion' => $consejo->accion,
                            ])
                        @endforeach
                    </div>
                    <i class="bi {{ $iconosCategoria[$categoria->value] ?? 'bi-stars' }} rec-panel-marca" aria-hidden="true"></i>
                </section>
            @endforeach
        </div>
    </div>
@endsection
