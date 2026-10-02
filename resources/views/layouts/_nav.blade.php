{{--
    Menú principal (a la izquierda, junto a la marca): seis secciones en lugar de nueve enlaces sueltos. Las que agrupan pantallas se abren
    como desplegable (Bootstrap); la sección queda marcada si la pantalla actual es una de las suyas.
--}}
@php
    $secciones = [
        ['texto' => 'Hoy', 'ruta' => 'hoy', 'activa' => ['hoy']],
        // Agenda y Calendario son los puntos fuertes: van a la vista, no dentro de un desplegable.
        ['texto' => 'Agenda', 'ruta' => 'agenda.index', 'activa' => ['agenda.*']],
        ['texto' => 'Calendario', 'ruta' => 'calendario.index', 'activa' => ['calendario.*']],
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

{{-- A la derecha: la cuenta. El invitado ve cómo guardar sus datos; la cuenta, su nombre y Salir. --}}
<ul class="navbar-nav align-items-lg-center nav-cuenta">
    @auth
        @if (auth()->user()->es_invitado)
            <li class="nav-item">
                <span class="nav-invitado" title="Tus datos están guardados solo en este navegador"><i class="bi bi-person" aria-hidden="true"></i> Invitado</span>
            </li>
            <li class="nav-item">
                @include('layouts._tema')
            </li>
            <li class="nav-item">
                <a class="nav-link nav-entrar" href="{{ route('login') }}" data-abrir-cuenta="entrar">Iniciar sesión</a>
            </li>
            <li class="nav-item">
                <a class="btn btn-foco btn-sm nav-guardar" href="{{ route('hoy', ['cuenta' => 'crear']) }}" data-abrir-cuenta="crear">
                    <i class="bi bi-cloud-check" aria-hidden="true"></i> Guardar mis datos
                </a>
            </li>
        @else
            <li class="nav-item">
                <span class="nav-usuario" title="{{ auth()->user()->email }}"><i class="bi bi-person-circle" aria-hidden="true"></i> {{ auth()->user()->nombreVisible() }}</span>
            </li>
            <li class="nav-item">
                @include('layouts._tema')
            </li>
            <li class="nav-item nav-salir">
                <form method="POST" action="{{ route('logout') }}" data-confirmar="¿Salir de tu cuenta? Vas a seguir como invitado, sin tus datos, hasta que vuelvas a iniciar sesión." data-confirmar-aceptar="Salir">
                    @csrf
                    <button type="submit" class="nav-link" title="Salir">
                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Salir</span>
                    </button>
                </form>
            </li>
        @endif
    @endauth
</ul>
