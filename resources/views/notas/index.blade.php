@extends('layouts.app')

@section('titulo', 'Notas · FocoDiario')

@push('head')
    @vite(['resources/css/notas.css', 'resources/js/notas.js'])
@endpush

@php
    $hayFiltros = $filtro || $busqueda !== '' || $colorFiltro || $soloFijadas;
    // Enlace de cada pastilla: conserva los demás filtros y cambia solo el suyo.
    $actuales = array_filter([
        'contexto' => $filtro,
        'q' => $busqueda !== '' ? $busqueda : null,
        'color' => $colorFiltro?->value,
        'fijadas' => $soloFijadas ? 1 : null,
    ]);
    $enlace = fn (array $cambios) => route('notas.index', array_filter(array_merge($actuales, $cambios), fn ($v) => $v !== null && $v !== ''));
@endphp

@section('contenido')
    <div class="notas" id="notas" data-vista="cuadricula">
        <header class="notas-cab">
            <div class="notas-titulos">
                <h1 class="notas-titulo">Notas</h1>
                <p class="notas-subtitulo" id="notas-subtitulo">
                    @if ($filtro === 'bandeja')
                        Bandeja de entrada ·
                    @elseif ($contextoFiltro)
                        {{ $contextoFiltro->rutaCompleta() }} ·
                    @endif
                    <span data-conteo>{{ $notas->count() }}</span> {{ $notas->count() === 1 ? 'nota' : 'notas' }}@if ($hayFiltros) de {{ $totalNotas }}@endif
                    @if ($totalFijadas > 0 && ! $hayFiltros) · {{ $totalFijadas }} {{ $totalFijadas === 1 ? 'fijada' : 'fijadas' }}@endif
                </p>
            </div>

            <div class="notas-acciones-cab">
                <div class="notas-vistas" role="group" aria-label="Vista de las notas" data-vistas hidden>
                    <button type="button" class="notas-vista" data-vista="cuadricula" aria-pressed="true" aria-label="Vista de cuadrícula" title="Cuadrícula"><i class="bi bi-grid" aria-hidden="true"></i></button>
                    <button type="button" class="notas-vista" data-vista="lista" aria-pressed="false" aria-label="Vista de lista" title="Lista"><i class="bi bi-list-ul" aria-hidden="true"></i></button>
                </div>
                <a href="{{ route('contextos.index') }}" class="btn btn-foco-suave notas-suave"><i class="bi bi-diagram-3" aria-hidden="true"></i> Contextos</a>
                <a href="{{ route('notas.create', $contextoFiltro ? ['contexto' => $contextoFiltro->id] : []) }}" class="btn btn-foco"
                   data-abrir-nota="crear" data-contexto="{{ $contextoFiltro?->id }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nueva nota</a>
            </div>
        </header>

        <form method="GET" action="{{ route('notas.index') }}" class="notas-filtros" role="search" aria-label="Buscar y filtrar notas">
            @if ($colorFiltro) <input type="hidden" name="color" value="{{ $colorFiltro->value }}"> @endif
            @if ($soloFijadas) <input type="hidden" name="fijadas" value="1"> @endif

            <label class="notas-buscar">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span class="visually-hidden">Buscar en las notas</span>
                <input type="search" name="q" value="{{ $busqueda }}" placeholder="Buscar en las notas" maxlength="100" autocomplete="off">
            </label>

            <label class="notas-materia">
                <span class="visually-hidden">Materia</span>
                <i class="bi bi-folder2" aria-hidden="true"></i>
                <select name="contexto" data-envia-al-cambiar>
                    <option value="">Todas las materias</option>
                    <option value="bandeja" @selected($filtro === 'bandeja')>Bandeja de entrada ({{ $totalBandeja }})</option>
                    @foreach ($destinos as $id => $ruta)
                        <option value="{{ $id }}" @selected((string) $filtro === (string) $id)>{{ $ruta }}</option>
                    @endforeach
                </select>
            </label>

            <div class="notas-pastillas" role="group" aria-label="Filtrar por color o fijadas">
                <a href="{{ $enlace(['fijadas' => $soloFijadas ? null : 1]) }}" class="notas-pastilla" @if ($soloFijadas) aria-current="true" @endif>
                    <i class="bi bi-pin-angle{{ $soloFijadas ? '-fill' : '' }}" aria-hidden="true"></i> Fijadas
                </a>
                @foreach (\App\Enums\ColorActividad::cases() as $c)
                    <a href="{{ $enlace(['color' => $colorFiltro === $c ? null : $c->value]) }}" class="notas-pastilla notas-pastilla-color"
                       style="--nota-fondo: {{ $c->fondo() }}; --nota-marca: {{ $c->marca() }}" aria-label="{{ $c->etiqueta() }}" title="{{ $c->etiqueta() }}"
                       @if ($colorFiltro === $c) aria-current="true" @endif>
                        <span class="notas-punto" aria-hidden="true"></span>
                    </a>
                @endforeach
                @if ($hayFiltros)
                    <a href="{{ route('notas.index') }}" class="notas-pastilla notas-limpiar"><i class="bi bi-x-lg" aria-hidden="true"></i> Quitar filtros</a>
                @endif
            </div>

            <noscript><button type="submit" class="btn btn-foco-suave notas-suave">Filtrar</button></noscript>
        </form>

        <div class="notas-lista" id="lista-notas">
            @foreach ($notas as $nota)
                @include('notas._nota')
            @endforeach
        </div>

        <div class="notas-vacio" data-vacio @if ($notas->isNotEmpty()) hidden @endif>
            <i class="bi bi-journal-text" aria-hidden="true"></i>
            @if ($hayFiltros)
                <p class="notas-vacio-titulo">Ninguna nota coincide con estos filtros.</p>
                <a href="{{ route('notas.index') }}" class="btn btn-foco-suave notas-suave">Quitar filtros</a>
            @else
                <p class="notas-vacio-titulo">Tu cuaderno está en blanco.</p>
                <p class="notas-vacio-texto">Anotá una idea, un apunte de clase o algo para no olvidar. También podés usar la nota rápida en Hoy.</p>
                <a href="{{ route('notas.create') }}" class="btn btn-foco" data-abrir-nota="crear"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nueva nota</a>
            @endif
        </div>
    </div>

    @include('notas._dialogo')
@endsection
