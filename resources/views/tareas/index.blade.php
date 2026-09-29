@extends('layouts.app')

@section('titulo', 'Tareas · FocoDiario')

@section('contenido')
    @php($cantidad = $vista === 'tablero' ? $total : $tareas->count())
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="pagina-titulo">Tareas</h1>
            <p class="text-secondary mb-0">{{ $cantidad }} {{ $cantidad === 1 ? 'tarea' : 'tareas' }}</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <nav class="selector-vista" aria-label="Vista de tareas">
                <a href="{{ route('tareas.index', array_filter(['vista' => 'lista', 'estado' => $estadoFiltro, 'proyecto' => $proyectoFiltro])) }}"
                   @if ($vista === 'lista') aria-current="page" @endif><i class="bi bi-list-ul"></i> Lista</a>
                <a href="{{ route('tareas.index', array_filter(['vista' => 'tablero', 'proyecto' => $proyectoFiltro])) }}"
                   @if ($vista === 'tablero') aria-current="page" @endif><i class="bi bi-kanban"></i> Tablero</a>
            </nav>
            <a href="{{ route('tareas.create') }}" class="btn btn-foco"><i class="bi bi-plus-lg"></i> Nueva tarea</a>
        </div>
    </div>

    <form method="GET" action="{{ route('tareas.index') }}" class="tarjeta tarjeta-relleno mb-3">
        @if ($vista === 'tablero')
            <input type="hidden" name="vista" value="tablero">
        @endif
        <div class="row g-3 align-items-end">
            @if ($vista === 'lista')
                <div class="col-sm-4 col-lg-3">
                    <label for="filtro-estado" class="form-label">Estado</label>
                    <select id="filtro-estado" name="estado" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($estados as $estado)
                            <option value="{{ $estado->value }}" @selected($estadoFiltro === $estado->value)>{{ $estado->etiqueta() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
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
                    <a href="{{ route('tareas.index', $vista === 'tablero' ? ['vista' => 'tablero'] : []) }}" class="btn btn-foco-suave">Quitar filtros</a>
                @endif
            </div>
        </div>
    </form>

    @if ($vista === 'tablero')
        @include('tareas._tablero')
    @else
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
    @endif

    <script>
        // Recuerda la última vista elegida; solo se aplica si la URL no trae ?vista=
        (function () {
            try {
                var url = new URL(window.location.href);
                if (!url.searchParams.has('vista')) {
                    if (localStorage.getItem('foco.vistaTareas') === 'tablero' && !url.searchParams.has('estado')) {
                        url.searchParams.set('vista', 'tablero');
                        window.location.replace(url.toString());
                    }
                    return;
                }
                localStorage.setItem('foco.vistaTareas', url.searchParams.get('vista'));
            } catch (e) {}
        })();
    </script>
@endsection
