<nav class="estudio-pestanas" aria-label="Secciones de Estudio">
    <a href="{{ route('estudio.index') }}" class="{{ request()->routeIs('estudio.index') ? 'activa' : '' }}" @if (request()->routeIs('estudio.index')) aria-current="page" @endif>Temporizador</a>
    <a href="{{ route('estudio.historial') }}" class="{{ request()->routeIs('estudio.historial') ? 'activa' : '' }}" @if (request()->routeIs('estudio.historial')) aria-current="page" @endif>Historial</a>
    <a href="{{ route('estudio.metodos') }}" class="{{ request()->routeIs('estudio.metodos') ? 'activa' : '' }}" @if (request()->routeIs('estudio.metodos')) aria-current="page" @endif>Formas de estudiar</a>
</nav>
