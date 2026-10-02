@extends('layouts.app')

@section('titulo', 'Tareas · FocoDiario')

@push('head')
    @vite(['resources/css/tareas.css', 'resources/js/tareas.js'])
@endpush

@section('contenido')
    @php
        $porDefecto = ['tipo' => 'todo', 'estado' => 'abiertas'];
        // Enlace a esta misma pantalla con algún filtro cambiado (los valores por defecto no viajan en la URL).
        $url = function (array $cambios = []) use ($filtros, $porDefecto) {
            $datos = array_filter(array_merge($filtros, $cambios), fn ($valor, $clave) => $valor !== null && $valor !== '' && ($porDefecto[$clave] ?? null) !== $valor, ARRAY_FILTER_USE_BOTH);

            return route('tareas.index', $datos);
        };
        $hayFiltros = $filtros['tipo'] !== 'todo' || $filtros['estado'] !== 'abiertas' || $filtros['prioridad'] || $filtros['proyecto'] || $filtros['q'];
        $grupoTiene = collect($grupos)->contains(fn ($items) => $items->isNotEmpty());
        $hayFilas = $grupoTiene || $completadas->isNotEmpty();
        $abiertas = $resumen['tareas'] + $resumen['recordatorios'];
    @endphp

    <div class="tareas" data-tareas data-estado="{{ $filtros['estado'] }}">
        <header class="t-cab">
            <div class="t-cab-titulo">
                <h1 class="pagina-titulo">Tareas</h1>
                <p class="t-resumen" data-resumen data-tareas="{{ $resumen['tareas'] }}" data-recordatorios="{{ $resumen['recordatorios'] }}" data-vencidas="{{ $resumen['vencidas'] }}">
                    <span data-r-tareas>{{ $resumen['tareas'] }} {{ $resumen['tareas'] === 1 ? 'tarea' : 'tareas' }}</span> y
                    <span data-r-recordatorios>{{ $resumen['recordatorios'] }} {{ $resumen['recordatorios'] === 1 ? 'recordatorio' : 'recordatorios' }}</span> abiertos
                    <span class="t-resumen-vencidas" data-r-vencidas @if ($resumen['vencidas'] === 0) hidden @endif>· {{ $resumen['vencidas'] }} {{ $resumen['vencidas'] === 1 ? 'vencida' : 'vencidas' }}</span>
                </p>
            </div>

            <div class="t-cab-acciones">
                <form method="GET" action="{{ route('tareas.index') }}" class="t-buscar" role="search">
                    @foreach (array_filter($filtros, fn ($valor, $clave) => $clave !== 'q' && $valor !== null && $valor !== '' && ($porDefecto[$clave] ?? null) !== $valor, ARRAY_FILTER_USE_BOTH) as $clave => $valor)
                        <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                    @endforeach
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <label for="t-buscar" class="visually-hidden">Buscar tareas y recordatorios</label>
                    <input type="search" id="t-buscar" name="q" value="{{ $filtros['q'] }}" placeholder="Buscar…" maxlength="100" autocomplete="off" data-buscar>
                </form>

                <a href="{{ route('tablero.index') }}" class="btn btn-foco-suave"><i class="bi bi-kanban" aria-hidden="true"></i> Tablero</a>

                <details class="t-nuevo" data-menu>
                    <summary class="btn btn-foco" aria-haspopup="menu"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nueva <i class="bi bi-chevron-down t-nuevo-flecha" aria-hidden="true"></i></summary>
                    <div class="t-menu" role="menu">
                        <a href="{{ route('tareas.create') }}" class="t-menu-item tipo-tarea" role="menuitem" data-abrir-tarea="nueva"><span class="t-punto" aria-hidden="true"></span> Tarea</a>
                        <a href="{{ route('recordatorios.create') }}" class="t-menu-item tipo-recordatorio" role="menuitem" data-abrir-recordatorio="nuevo"><span class="t-punto" aria-hidden="true"></span> Recordatorio</a>
                    </div>
                </details>
            </div>
        </header>

        <div class="t-filtros" role="group" aria-label="Filtros">
            <nav class="t-pills" aria-label="Tipo">
                @foreach (['todo' => 'Todo', 'tarea' => 'Tareas', 'recordatorio' => 'Recordatorios'] as $valor => $texto)
                    <a href="{{ $url(['tipo' => $valor]) }}" class="t-pill" @if ($filtros['tipo'] === $valor) aria-current="true" @endif>{{ $texto }}</a>
                @endforeach
            </nav>
            <span class="t-sep" aria-hidden="true"></span>
            <nav class="t-pills" aria-label="Estado">
                @foreach (['abiertas' => 'Abiertas', 'hechas' => 'Hechas'] as $valor => $texto)
                    <a href="{{ $url(['estado' => $valor]) }}" class="t-pill" @if ($filtros['estado'] === $valor) aria-current="true" @endif>{{ $texto }}</a>
                @endforeach
            </nav>
            <span class="t-sep" aria-hidden="true"></span>
            <nav class="t-pills" aria-label="Prioridad">
                @foreach (\App\Enums\PrioridadTarea::cases() as $prioridad)
                    <a href="{{ $url(['prioridad' => $filtros['prioridad'] === $prioridad->value ? null : $prioridad->value]) }}" class="t-pill"
                       @if ($filtros['prioridad'] === $prioridad->value) aria-current="true" @endif><span class="t-prio t-prio-{{ $prioridad->value }}" aria-hidden="true"></span> {{ $prioridad->etiqueta() }}</a>
                @endforeach
            </nav>
            @if ($proyectos->isNotEmpty())
                <details class="t-proyectos" data-menu>
                    <summary class="t-pill" @if ($filtros['proyecto']) aria-current="true" @endif aria-haspopup="menu">
                        <i class="bi bi-folder2" aria-hidden="true"></i> {{ $filtros['proyecto'] ?? 'Proyecto' }} <i class="bi bi-chevron-down t-nuevo-flecha" aria-hidden="true"></i>
                    </summary>
                    <div class="t-menu" role="menu">
                        <a href="{{ $url(['proyecto' => null]) }}" class="t-menu-item" role="menuitem">Todos los proyectos</a>
                        @foreach ($proyectos as $proyecto)
                            <a href="{{ $url(['proyecto' => $proyecto]) }}" class="t-menu-item" role="menuitem" @if ($filtros['proyecto'] === $proyecto) aria-current="true" @endif>{{ $proyecto }}</a>
                        @endforeach
                    </div>
                </details>
            @endif
            @if ($hayFiltros)
                <a href="{{ route('tareas.index') }}" class="t-limpiar"><i class="bi bi-x-lg" aria-hidden="true"></i> Limpiar filtros</a>
            @endif
        </div>

        <div class="t-lista" data-lista>
            @foreach ($grupos as $clave => $items)
                <section class="t-grupo t-grupo-{{ $clave }}" data-grupo="{{ $clave }}" aria-labelledby="t-grupo-{{ $clave }}" @if ($items->isEmpty()) hidden @endif>
                    <h2 class="t-grupo-titulo" id="t-grupo-{{ $clave }}">
                        <span>{{ \App\Services\Tareas\ListaTareas::GRUPOS[$clave] }}</span>
                        <span class="t-grupo-cuenta" data-cuenta>{{ $items->count() }}</span>
                    </h2>
                    <ul class="t-items" role="list" data-items>
                        @foreach ($items as $item)
                            @include('tareas._item')
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <details class="t-grupo t-grupo-completadas" data-grupo="completadas" @if ($filtros['estado'] === 'hechas') open @endif @if ($completadas->isEmpty() && $filtros['estado'] !== 'hechas') hidden @endif>
                <summary class="t-grupo-titulo">
                    <h2 id="t-grupo-completadas"><span>Completadas</span> <span class="t-grupo-cuenta" data-cuenta>{{ $hechasTotal }}</span></h2>
                    <i class="bi bi-chevron-down t-grupo-flecha" aria-hidden="true"></i>
                </summary>
                <ul class="t-items" role="list" data-items>
                    @foreach ($completadas as $item)
                        @include('tareas._item')
                    @endforeach
                </ul>
            </details>

            <p class="t-vacio" data-vacio @if ($hayFilas) hidden @endif>
                @if ($hayFiltros)
                    Nada coincide con los filtros. <a href="{{ route('tareas.index') }}">Limpiar filtros</a>
                @else
                    Todavía no cargaste nada. Empezá con el botón "Nueva".
                @endif
            </p>
            <p class="t-vacio" data-sin-resultados hidden>Ninguna fila coincide con la búsqueda.</p>
        </div>
    </div>

    @include('tareas._dialogo-tarea', ['proyectos' => $proyectos])
    @include('tareas._dialogo-recordatorio')
@endsection
