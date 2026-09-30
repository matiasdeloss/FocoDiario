@extends('layouts.app')

@section('titulo', 'Tablero Kanban · FocoDiario')

@push('head')
    @vite(['resources/css/tablero.css', 'resources/js/tablero.js'])
@endpush

@section('contenido')
    <div class="kanban">
        <header class="k-cab">
            <div class="k-cab-titulo">
                <h1 class="pagina-titulo">Tablero Kanban</h1>
                <p class="k-resumen" data-resumen>
                    <span data-total>{{ $total }}</span> {{ $total === 1 ? 'tarjeta' : 'tarjetas' }} en {{ $columnas->count() }} {{ $columnas->count() === 1 ? 'columna' : 'columnas' }}
                </p>
            </div>

            <div class="k-cab-acciones">
                <div class="k-filtros" role="search" aria-label="Filtrar tarjetas" hidden data-requiere-js data-filtros>
                    <div class="k-buscar">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <label for="k-buscar" class="visually-hidden">Buscar tarjetas</label>
                        <input type="search" id="k-buscar" placeholder="Buscar…" maxlength="100" autocomplete="off" data-filtro="q">
                    </div>
                    <label for="k-proyecto" class="visually-hidden">Filtrar por proyecto</label>
                    <select id="k-proyecto" class="form-select k-select" data-filtro="proyecto">
                        <option value="">Todos los proyectos</option>
                        @foreach ($proyectos as $proyecto)
                            <option value="{{ $proyecto }}" @selected($proyectoInicial === $proyecto)>{{ $proyecto }}</option>
                        @endforeach
                    </select>
                    <label for="k-prioridad" class="visually-hidden">Filtrar por prioridad</label>
                    <select id="k-prioridad" class="form-select k-select" data-filtro="prioridad">
                        <option value="">Toda prioridad</option>
                        @foreach (\App\Enums\PrioridadTarea::cases() as $prioridad)
                            <option value="{{ $prioridad->value }}">{{ $prioridad->etiqueta() }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="k-limpiar" data-limpiar hidden>Limpiar</button>
                </div>

                <a href="{{ route('tareas.index') }}" class="btn btn-foco-suave"><i class="bi bi-list-check" aria-hidden="true"></i> Tareas</a>
                <a href="{{ route('tareas.create') }}" class="btn btn-foco" data-abrir-tarea="nueva"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nueva tarea</a>
            </div>
        </header>

        @include('tablero._columnas')
    </div>

    @include('tareas._dialogo-tarea', ['proyectos' => $proyectos])
    @include('tablero._dialogos-columna')
@endsection
