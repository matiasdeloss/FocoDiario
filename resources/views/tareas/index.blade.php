@extends('layouts.app')

@section('titulo', 'Tareas · FocoDiario')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="pagina-titulo">Tareas</h1>
            <p class="text-secondary mb-0">{{ $tareas->count() }} {{ $tareas->count() === 1 ? 'tarea' : 'tareas' }}</p>
        </div>
        <a href="{{ route('tareas.create') }}" class="btn btn-foco"><i class="bi bi-plus-lg"></i> Nueva tarea</a>
    </div>

    <form method="GET" action="{{ route('tareas.index') }}" class="tarjeta p-3 mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-sm-4 col-lg-3">
                <label for="filtro-estado" class="form-label">Estado</label>
                <select id="filtro-estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($estados as $estado)
                        <option value="{{ $estado->value }}" @selected($estadoFiltro === $estado->value)>{{ $estado->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-lg-3">
                <label for="filtro-proyecto" class="form-label">Proyecto</label>
                <select id="filtro-proyecto" name="proyecto" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($proyectos as $proyecto)
                        <option value="{{ $proyecto }}" @selected($proyectoFiltro === $proyecto)>{{ $proyecto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 d-flex gap-2">
                <button type="submit" class="btn btn-foco-suave">Filtrar</button>
                @if ($estadoFiltro || $proyectoFiltro)
                    <a href="{{ route('tareas.index') }}" class="btn btn-foco-suave">Quitar filtros</a>
                @endif
            </div>
        </div>
    </form>

    <div class="tarjeta">
        @if ($tareas->isEmpty())
            <p class="estado-vacio px-4">
                @if ($estadoFiltro || $proyectoFiltro)
                    Ninguna tarea coincide con los filtros.
                @else
                    Todavía no cargaste tareas. Empezá con el botón "Nueva tarea".
                @endif
            </p>
        @else
            <div class="tabla-foco-contenedor">
                <table class="table tabla-foco">
                    <thead>
                        <tr>
                            <th scope="col">Tarea</th>
                            <th scope="col">Fecha límite</th>
                            <th scope="col">Prioridad</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Cambiar estado</th>
                            <th scope="col"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tareas as $tarea)
                            @include('tareas._fila')
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
