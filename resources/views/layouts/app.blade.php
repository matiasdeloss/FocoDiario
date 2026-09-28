<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'FocoDiario')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono&family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('hoy') ? 'active' : '' }}" href="{{ route('hoy') }}">Hoy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('tareas.*') ? 'active' : '' }}" href="{{ route('tareas.index') }}">Tareas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('recordatorios.*') ? 'active' : '' }}" href="{{ route('recordatorios.index') }}">Recordatorios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('registro.*') ? 'active' : '' }}" href="{{ route('registro.index') }}">Registro</a>
                    </li>
                    {{-- menu:notas --}}
                    {{-- menu:recomendaciones --}}
                </ul>
            </div>
        </div>
    </nav>

    <main class="container-fluid px-3 px-lg-5 py-4">
        @if (session('estado'))
            <div class="aviso-foco" role="status">{{ session('estado') }}</div>
        @endif
        @yield('contenido')
    </main>
</body>
</html>
