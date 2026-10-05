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
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    {{-- Aplica el tema guardado antes de pintar (sin destello). Lleva el nonce de la CSP; el interruptor está en resources/js/tema.js. --}}
    <script nonce="{{ Vite::cspNonce() }}">try{var t=localStorage.getItem('foco-tema');if(t==='light'||t==='dark')document.documentElement.dataset.theme=t}catch(e){}</script>
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
            {{-- Mini-temporizador: con una sesión en curso se ve en todas las pantallas. En escritorio queda
                 a la izquierda de la cuenta; en celular, en su propia fila debajo de la marca. --}}
            @include('layouts._pomodoro-widget')
            <div class="collapse navbar-collapse" id="menu">
                @include('layouts._nav')
            </div>
        </div>
    </nav>

    <main class="container-fluid px-3 px-lg-5 principal-foco">
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

    @if (auth()->user()?->es_invitado)
        @include('layouts._dialogo-cuenta')
    @endif

    {{-- Avisos (toasts): un solo contenedor para toda la app (resources/js/avisos.js). El mensaje de la sesión flash
         queda en un nodo oculto que el script convierte en aviso; no hay banner visible en la página. --}}
    <div id="avisos-flotantes" class="avisos-flotantes" popover="manual"></div>
    @if (session('estado'))
        <div hidden data-aviso-flash data-tipo="{{ session('estado_tipo', 'exito') }}" data-texto="{{ session('estado') }}"
             @if (session('estado_detalle')) data-detalle="{{ session('estado_detalle') }}" @endif></div>
    @endif
</body>
</html>
