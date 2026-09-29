<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'FocoDiario')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500&family=Figtree:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-foco">
        <div class="container-fluid px-3 px-lg-5">
            <a class="navbar-brand" href="{{ route('hoy') }}">
                <i class="bi bi-bullseye"></i> FocoDiario
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
                    aria-controls="menu" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            {{-- En /estudio y en Hoy hay una tarjeta propia del temporizador: el mini-temporizador no se duplica. --}}
            @unless (request()->routeIs('estudio.index', 'hoy'))
                @include('layouts._pomodoro-widget')
            @endunless
            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('hoy') ? 'active' : '' }}" href="{{ route('hoy') }}" @if (request()->routeIs('hoy')) aria-current="page" @endif>Hoy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('tareas.*') ? 'active' : '' }}" href="{{ route('tareas.index') }}" @if (request()->routeIs('tareas.*')) aria-current="page" @endif>Tareas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('recordatorios.*') ? 'active' : '' }}" href="{{ route('recordatorios.index') }}" @if (request()->routeIs('recordatorios.*')) aria-current="page" @endif>Recordatorios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('registro.*') ? 'active' : '' }}" href="{{ route('registro.index') }}" @if (request()->routeIs('registro.*')) aria-current="page" @endif>Registro</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('notas.*', 'contextos.*') ? 'active' : '' }}" href="{{ route('notas.index') }}" @if (request()->routeIs('notas.*', 'contextos.*')) aria-current="page" @endif>Notas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('recomendaciones.*') ? 'active' : '' }}" href="{{ route('recomendaciones.index') }}" @if (request()->routeIs('recomendaciones.*')) aria-current="page" @endif>Recomendaciones</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('estudio.*') ? 'active' : '' }}" href="{{ route('estudio.index') }}" @if (request()->routeIs('estudio.*')) aria-current="page" @endif>Estudio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('calendario.*') ? 'active' : '' }}" href="{{ route('calendario.index') }}" @if (request()->routeIs('calendario.*')) aria-current="page" @endif>Calendario</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container-fluid px-3 px-lg-5 principal-foco">
        @if (session('estado'))
            <div class="aviso-foco" role="status">{{ session('estado') }}</div>
        @endif
        @yield('contenido')
    </main>

    <footer class="footer-foco">
        <div class="container-fluid px-3 px-lg-5">
            <div class="footer-foco-contenido">
                <div>
                    <a class="footer-foco-marca" href="{{ route('hoy') }}">
                        <i class="bi bi-bullseye"></i> FocoDiario
                    </a>
                    <p class="footer-foco-texto mb-0">Menos ocio, más foco. Un día a la vez.</p>
                </div>
                <nav class="footer-foco-enlaces" aria-label="Secciones">
                    <a href="{{ route('tareas.index') }}">Tareas</a>
                    <a href="{{ route('estudio.index') }}">Estudio</a>
                    <a href="{{ route('calendario.index') }}">Calendario</a>
                    <a href="{{ route('notas.index') }}">Notas</a>
                    <a href="{{ route('recomendaciones.index') }}">Recomendaciones</a>
                </nav>
            </div>
            <p class="footer-foco-pie mb-0">
                &copy; {{ now()->year }} FocoDiario &middot; Uso personal. Tus datos se guardan en tu equipo.
            </p>
        </div>
    </footer>
</body>
</html>
