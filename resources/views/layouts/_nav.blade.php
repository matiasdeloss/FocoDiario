{{--
    Menú principal (a la izquierda, junto a la marca): cinco secciones en lugar de nueve enlaces sueltos. Las que agrupan pantallas se abren
    como desplegable (Bootstrap); la sección queda marcada si la pantalla actual es una de las suyas.
--}}
@php
    $secciones = [
        ['texto' => 'Hoy', 'ruta' => 'hoy', 'activa' => ['hoy']],
        ['texto' => 'Planificar', 'id' => 'planificar', 'activa' => ['agenda.*', 'calendario.*'], 'items' => [
            ['texto' => 'Agenda', 'ruta' => 'agenda.index', 'activa' => ['agenda.*'], 'icono' => 'journal-text'],
            ['texto' => 'Calendario', 'ruta' => 'calendario.index', 'activa' => ['calendario.*'], 'icono' => 'calendar3'],
        ]],
        ['texto' => 'Tareas', 'id' => 'tareas', 'activa' => ['tareas.*', 'recordatorios.*', 'tablero.*'], 'items' => [
            ['texto' => 'Lista', 'ruta' => 'tareas.index', 'activa' => ['tareas.*', 'recordatorios.*'], 'icono' => 'list-check'],
            ['texto' => 'Tablero', 'ruta' => 'tablero.index', 'activa' => ['tablero.*'], 'icono' => 'kanban'],
        ]],
        ['texto' => 'Estudio', 'id' => 'estudio', 'activa' => ['estudio.*', 'registro.*', 'recomendaciones.*'], 'items' => [
            ['texto' => 'Temporizador', 'ruta' => 'estudio.index', 'activa' => ['estudio.*'], 'icono' => 'stopwatch'],
            ['texto' => 'Registro de tiempo', 'ruta' => 'registro.index', 'activa' => ['registro.*'], 'icono' => 'hourglass-split'],
            ['texto' => 'Recomendaciones', 'ruta' => 'recomendaciones.index', 'activa' => ['recomendaciones.*'], 'icono' => 'lightbulb'],
        ]],
        ['texto' => 'Notas', 'ruta' => 'notas.index', 'activa' => ['notas.*', 'contextos.*']],
    ];
@endphp

<ul class="navbar-nav me-auto ms-lg-3 align-items-lg-center">
    @foreach ($secciones as $seccion)
        @php($activa = request()->routeIs(...$seccion['activa']))

        @isset($seccion['items'])
            <li class="nav-item dropdown">
                <button type="button" @class(['nav-link', 'dropdown-toggle', 'active' => $activa]) id="menu-{{ $seccion['id'] }}"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    {{ $seccion['texto'] }}
                </button>
                <ul class="dropdown-menu" aria-labelledby="menu-{{ $seccion['id'] }}">
                    @foreach ($seccion['items'] as $item)
                        @php($actual = request()->routeIs(...$item['activa']))
                        <li>
                            <a @class(['dropdown-item', 'active' => $actual]) href="{{ route($item['ruta']) }}" @if ($actual) aria-current="page" @endif>
                                <i class="bi bi-{{ $item['icono'] }}" aria-hidden="true"></i> {{ $item['texto'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>
        @else
            <li class="nav-item">
                <a @class(['nav-link', 'active' => $activa]) href="{{ route($seccion['ruta']) }}" @if ($activa) aria-current="page" @endif>{{ $seccion['texto'] }}</a>
            </li>
        @endisset
    @endforeach
</ul>

{{-- Salir queda solo, a la derecha. --}}
<ul class="navbar-nav align-items-lg-center">
    @auth
        <li class="nav-item nav-salir">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-link" title="Salir">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span class="d-lg-none"> Salir</span><span class="visually-hidden d-none d-lg-inline">Salir</span>
                </button>
            </form>
        </li>
    @endauth
</ul>
