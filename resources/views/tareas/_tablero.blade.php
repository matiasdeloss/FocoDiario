@php
    $estadosTablero = \App\Enums\EstadoTarea::cases();
    $etiquetas = collect($estadosTablero)->mapWithKeys(fn ($e) => [$e->value => $e->etiqueta()]);
@endphp
<div id="tablero" data-url-estado="{{ url('tareas/__ID__/estado') }}"
     data-orden="{{ json_encode(array_map(fn ($e) => $e->value, $estadosTablero)) }}"
     data-etiquetas="{{ json_encode($etiquetas) }}">
    <div id="tablero-aviso" class="aviso-foco" role="alert" hidden></div>

    <div class="tablero">
        @foreach ($columnas as $columna)
            @php($estado = $columna['estado'])
            <section class="tablero-columna" aria-labelledby="col-{{ $estado->value }}" data-estado="{{ $estado->value }}">
                <header class="tablero-columna-cabecera">
                    <h2 id="col-{{ $estado->value }}" class="tablero-columna-titulo"><i class="bi {{ $estado->icono() }}"></i> {{ $estado->etiqueta() }}</h2>
                    <span class="badge-foco {{ $estado->claseBadge() }}" data-contador data-ocultas="{{ $columna['ocultas'] }}">{{ $columna['tareas']->count() + $columna['ocultas'] }}</span>
                </header>
                <div class="tablero-lista" data-lista>
                    @foreach ($columna['tareas'] as $tarea)
                        @include('tareas._tarjeta')
                    @endforeach
                </div>
                <p class="estado-vacio tablero-vacio" data-vacio @if ($columna['tareas']->isNotEmpty()) hidden @endif>
                    @if ($estado === \App\Enums\EstadoTarea::Completada)
                        Todavía no completaste ninguna. Arrastrá una tarea acá cuando la termines.
                    @elseif ($estado === \App\Enums\EstadoTarea::EnProgreso)
                        Nada en marcha. Traé acá una tarea cuando la empieces.
                    @else
                        Sin tareas pendientes.
                    @endif
                </p>
                @if ($columna['ocultas'] > 0)
                    <a class="tablero-ver-todas" href="{{ route('tareas.index', array_filter(['estado' => 'completada', 'proyecto' => $proyectoFiltro])) }}">
                        Ver todas las completadas ({{ $columna['tareas']->count() + $columna['ocultas'] }})
                    </a>
                @endif
            </section>
        @endforeach
    </div>
</div>
