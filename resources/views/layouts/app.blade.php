<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="url-csrf" content="{{ route('csrf') }}">
    <meta name="url-login" content="{{ route('login') }}">
    <meta name="url-recordatorios-vencidos" content="{{ route('recordatorios.vencidos') }}">
    <title>@yield('titulo', 'FocoDiario')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-foco" aria-label="Principal">
        <div class="container-fluid px-3 px-lg-5">
            <a class="navbar-brand" href="{{ route('hoy') }}">
                <i class="bi bi-bullseye" aria-hidden="true"></i> FocoDiario
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
                @include('layouts._nav')
            </div>
        </div>
    </nav>

    <main class="container-fluid px-3 px-lg-5 principal-foco">
        @if (session('estado'))
            <div class="aviso-foco" role="status" data-aviso-flash>{{ session('estado') }}</div>
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
                    <a href="{{ route('hoy') }}">Hoy</a>
                    <a href="{{ route('agenda.index') }}">Agenda</a>
                    <a href="{{ route('tareas.index') }}">Tareas</a>
                    <a href="{{ route('estudio.index') }}">Estudio</a>
                    <a href="{{ route('notas.index') }}">Notas</a>
                </nav>
            </div>
            <p class="footer-foco-pie mb-0">
                &copy; {{ now()->year }} FocoDiario &middot; Uso personal, sin publicidad ni rastreo.
            </p>
        </div>
    </footer>
</body>
</html>
