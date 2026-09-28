{{-- Mini-temporizador de estudio. Oculto hasta que el motor (pomodoro-motor.js) encuentra una sesión en curso. --}}
<div id="pomodoro-widget" class="pomodoro-mini order-md-last" role="group" aria-label="Temporizador de estudio"
     data-fase="foco" data-pausado="false" data-url-sesiones="{{ route('estudio.sesiones.store') }}" hidden>
    <div class="pomodoro-mini-info">
        <span class="pomodoro-mini-fase" data-w="fase"></span>
        <span class="pomodoro-mini-tiempo" data-w="tiempo" role="timer" aria-live="off">00:00</span>
        <span class="pomodoro-mini-barra" data-w="barra" aria-hidden="true"></span>
    </div>
    <div class="pomodoro-mini-botones">
        <button type="button" class="pomodoro-mini-boton" data-w-accion="pausa" aria-label="Pausar temporizador" title="Pausar">
            <i class="bi bi-pause-fill" aria-hidden="true"></i>
        </button>
        <button type="button" class="pomodoro-mini-boton" data-w-accion="saltar" aria-label="Saltar a la siguiente fase" title="Saltar fase">
            <i class="bi bi-skip-forward-fill" aria-hidden="true"></i>
        </button>
        <button type="button" class="pomodoro-mini-boton pomodoro-mini-boton-peligro" data-w-accion="terminar" aria-label="Terminar sesión de estudio" title="Terminar sesión">
            <i class="bi bi-stop-fill" aria-hidden="true"></i>
        </button>
        <a class="pomodoro-mini-boton" href="{{ route('estudio.index') }}" aria-label="Ir a Estudio" title="Ir a Estudio">
            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
        </a>
    </div>
    <span class="visually-hidden" role="status" aria-live="polite" data-w="anuncio"></span>
</div>
