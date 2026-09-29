{{-- Pomodoro de Hoy. Sin lógica propia: hoy-pomodoro.js se suscribe al motor único (pomodoro-motor.js), el mismo de Estudio. Requiere: $pomodorosHoy, $sesionActivaId --}}
<section id="hoy-pomodoro" class="hoy-tarjeta hoy-lateral hoy-pomodoro" aria-labelledby="hoy-pomodoro-titulo"
         data-hoy-pomodoro data-fase="inactivo" data-pausado="false"
         data-url-sesiones="{{ route('estudio.sesiones.store') }}"
         data-sesion-activa="{{ $sesionActivaId }}" data-completados-hoy="{{ $pomodorosHoy }}">
    <div class="hoy-tarjeta-cab">
        <h2 class="hoy-tarjeta-titulo" id="hoy-pomodoro-titulo"><i class="bi bi-stopwatch hoy-titulo-icono" aria-hidden="true"></i>Pomodoro</h2>
        <span class="hoy-puntos" data-puntos role="img"
              aria-label="{{ $pomodorosHoy }} {{ $pomodorosHoy === 1 ? 'pomodoro completado' : 'pomodoros completados' }} hoy"
              title="{{ $pomodorosHoy }} hoy">
            @foreach (range(1, 4) as $n)
                <span class="hoy-punto-ronda {{ $n <= $pomodorosHoy ? 'es-lleno' : '' }}"></span>
            @endforeach
        </span>
    </div>

    <div class="hoy-modos" role="group" aria-label="Modo del temporizador">
        <button type="button" class="hoy-modo" data-modo="foco" aria-pressed="true">Enfoque</button>
        <button type="button" class="hoy-modo" data-modo="descanso" aria-pressed="false">Descanso</button>
        <button type="button" class="hoy-modo" data-modo="largo" aria-pressed="false">Pausa larga</button>
    </div>

    <div class="hoy-anillo">
        <svg viewBox="0 0 96 96" width="112" height="112" aria-hidden="true" focusable="false">
            <circle class="hoy-anillo-fondo" cx="48" cy="48" r="43"></circle>
            <circle class="hoy-anillo-progreso" cx="48" cy="48" r="43" data-anillo></circle>
        </svg>
        <div class="hoy-anillo-centro">
            <span class="hoy-reloj" data-reloj role="timer" aria-live="off">25:00</span>
            <span class="hoy-fase" data-fase-texto>LISTO</span>
        </div>
    </div>

    <div class="hoy-pomodoro-botones">
        <button type="button" class="hoy-boton-redondo hoy-boton-suave" data-accion="reiniciar" aria-label="Reiniciar temporizador" title="Reiniciar" disabled>
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
        </button>
        <button type="button" class="hoy-boton hoy-boton-principal" data-accion="principal">Iniciar</button>
    </div>

    <div class="hoy-pomodoro-extra" data-extra hidden>
        <button type="button" class="hoy-boton-texto" data-accion="saltar">Saltar fase</button>
        <button type="button" class="hoy-boton-texto" data-accion="terminar">Terminar sesión</button>
    </div>

    <p class="hoy-mensaje" role="status" data-mensaje></p>
    <span class="hoy-solo-lector" role="status" aria-live="polite" data-anuncio></span>

    <a href="{{ route('estudio.index') }}" class="hoy-enlace hoy-enlace-pie">Ajustar tiempos y tema en Estudio</a>
</section>
