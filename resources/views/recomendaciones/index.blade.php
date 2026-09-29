@extends('layouts.app')

@section('titulo', 'Recomendaciones · FocoDiario')

@push('head')
    @vite(['resources/css/recomendaciones.css'])
@endpush

@section('contenido')
    @php
        $total = $paraTi->count() + $ideas->count();
        $urlFiltro = fn (?string $valor) => route('recomendaciones.index', $valor ? ['categoria' => $valor] : []);
    @endphp
    <div class="rec-pagina">
        <header class="rec-encabezado">
            <div>
                <h1 class="pagina-titulo">Recomendaciones</h1>
                <p class="rec-resumen">
                    {{ $total }} {{ $total === 1 ? 'recomendación' : 'recomendaciones' }} hoy:
                    {{ $paraTi->count() }} según tus datos y {{ $ideas->count() }} ideas de estudio y vida.
                    @if ($alertas > 0)
                        <span class="rec-resumen-alerta">{{ $alertas }} {{ $alertas === 1 ? 'alerta' : 'alertas' }}.</span>
                    @endif
                </p>
            </div>
            <nav class="rec-filtros" aria-label="Filtrar por categoría">
                <a href="{{ $urlFiltro(null) }}" class="rec-filtro {{ $categoria ? '' : 'es-activo' }}" @if (! $categoria) aria-current="true" @endif>Todas</a>
                @foreach ($categorias as $opcion)
                    <a href="{{ $urlFiltro($opcion->value) }}" class="rec-filtro {{ $categoria === $opcion ? 'es-activo' : '' }}" @if ($categoria === $opcion) aria-current="true" @endif>{{ $opcion->etiqueta() }}</a>
                @endforeach
            </nav>
        </header>

        <div class="rec-columnas">
            <section class="rec-columna" aria-labelledby="rec-para-ti">
                <h2 class="rec-columna-titulo" id="rec-para-ti">Para ti ahora <span class="rec-columna-cuenta">{{ $paraTi->count() }}</span></h2>
                <p class="rec-columna-sub">Según tu registro de hoy, tus tareas y recordatorios.</p>
                @forelse ($paraTi as $recomendacion)
                    @include('recomendaciones._card', [
                        'clase' => 'rec-tono-'.$recomendacion->tipo->value,
                        'icono' => $recomendacion->icono,
                        'pill' => $recomendacion->tipo->etiqueta(),
                        'titulo' => $recomendacion->titulo,
                        'texto' => $recomendacion->mensaje,
                        'datos' => $recomendacion->datos,
                        'fuente' => $recomendacion->fuente,
                    ])
                @empty
                    <p class="estado-vacio">Nada urgente en esta categoría por ahora.</p>
                @endforelse
            </section>

            <section class="rec-columna" aria-labelledby="rec-ideas">
                <h2 class="rec-columna-titulo" id="rec-ideas">Ideas para tu día <span class="rec-columna-cuenta">{{ $ideas->count() }}</span></h2>
                <p class="rec-columna-sub">Estudio y bienestar. Cambian cada día.</p>
                @foreach ($ideas as $consejo)
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
            </section>
        </div>
    </div>
@endsection
