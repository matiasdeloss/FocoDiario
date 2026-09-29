@extends('layouts.app')

@section('titulo', 'Formas de estudiar · FocoDiario')

@php
    $nivelesEvidencia = [
        'alta' => ['Evidencia alta', 'badge-estado-completada'],
        'moderada' => ['Evidencia moderada', 'badge-estado-en-curso'],
        'poca' => ['Evidencia poca o discutida', 'badge-estado-pendiente'],
    ];
@endphp

@section('contenido')
    <div class="contenido-lectura">
        <div class="mb-3">
            <h1 class="pagina-titulo">Estudio</h1>
            <p class="text-secondary mb-0">Fichas de métodos con pasos concretos, cuándo usarlos, el error más común y qué evidencia tienen.</p>
        </div>

        @include('estudio._pestanas')

        <div class="tarjeta tarjeta-relleno mb-3 nota-metodos" role="note">
            <h2 class="tarjeta-titulo">Sobre los "estilos de aprendizaje"</h2>
            <p class="mb-2">{{ $notaEstilos }}</p>
            <p class="small mb-0"><a href="{{ $notaEstilosFuente[1] }}" target="_blank" rel="noopener noreferrer">{{ $notaEstilosFuente[0] }}</a></p>
        </div>

        <nav class="metodos-indice mb-4" aria-label="Métodos">
            @foreach ($metodos as $metodo)
                <a href="#{{ $metodo['clave'] }}">{{ $metodo['nombre'] }}</a>
            @endforeach
        </nav>

        <p class="small text-secondary mb-3">Niveles de evidencia: alta si hay varias revisiones o metaanálisis a favor, moderada si hay respaldo parcial o inferido de prácticas relacionadas, poca o discutida si hay pocos estudios o resultados mixtos. Es una clasificación resumida, no un veredicto.</p>

        <div class="row g-3">
            @foreach ($metodos as $metodo)
                @php [$etiquetaNivel, $claseNivel] = $nivelesEvidencia[$metodo['evidencia']['nivel']]; @endphp
                <div class="col-lg-6">
                    <article id="{{ $metodo['clave'] }}" class="tarjeta tarjeta-relleno h-100 ficha-metodo">
                        <header class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                            <h2 class="h4 mb-0">{{ $metodo['nombre'] }}</h2>
                            <span class="badge-foco {{ $claseNivel }}">{{ $etiquetaNivel }}</span>
                        </header>

                        <h3 class="ficha-titulo">Qué es</h3>
                        <p>{{ $metodo['que_es'] }}</p>

                        <h3 class="ficha-titulo">Pasos</h3>
                        <ol class="ficha-pasos">
                            @foreach ($metodo['pasos'] as $paso)
                                <li>{{ $paso }}</li>
                            @endforeach
                        </ol>

                        <h3 class="ficha-titulo">Cuándo usarla</h3>
                        <p>{{ $metodo['cuando'] }}</p>

                        <h3 class="ficha-titulo">Error común</h3>
                        <p>{{ $metodo['error'] }}</p>

                        <h3 class="ficha-titulo">Evidencia</h3>
                        <p class="mb-2">{{ $metodo['evidencia']['texto'] }}</p>
                        <ul class="ficha-fuentes">
                            @foreach ($metodo['evidencia']['fuentes'] as [$titulo, $url])
                                <li><a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $titulo }}</a></li>
                            @endforeach
                        </ul>

                        @if ($metodo['preset'])
                            <a href="{{ route('estudio.index', ['preset' => $metodo['preset']]) }}" class="btn btn-foco mt-2">
                                <i class="bi bi-stopwatch"></i> Usar este método
                            </a>
                        @endif
                    </article>
                </div>
            @endforeach
        </div>
    </div>
@endsection
