@extends('layouts.app')

@section('titulo', 'Estudio · FocoDiario')

@section('contenido')
    <div class="mb-3">
        <h1 class="pagina-titulo">Estudio</h1>
        <p class="text-secondary mb-0">Foco y descanso con registro del tiempo real, incluido el tiempo libre entre pomodoros.</p>
    </div>

    @include('estudio._pestanas')

    <div id="pomodoro" class="row g-3"
         data-fase="inactivo"
         data-url-sesiones="{{ route('estudio.sesiones.store') }}"
         data-sesion-activa="{{ $sesionActivaId }}"
         data-presets='@json($presets)'>

        <div class="col-lg-7">
            <div class="tarjeta tarjeta-relleno h-100 pomodoro-principal">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge-foco pomodoro-fase" data-p="fase">Listo para empezar</span>
                    <span class="lista-fila-meta" data-p="ciclo"></span>
                </div>

                <div class="text-center py-3">
                    <div class="pomodoro-tiempo pomodoro-tiempo-grande" data-p="tiempo" role="timer" aria-live="off">25:00</div>
                    <div class="pomodoro-puntos mt-3" data-p="puntos" aria-hidden="true"></div>
                    <p class="text-secondary mt-3 mb-0" data-p="ayuda" aria-live="polite"></p>
                </div>

                <div class="d-flex flex-wrap justify-content-center gap-2 mb-2">
                    <button type="button" class="btn btn-foco btn-lg-foco" data-p-accion="iniciar" data-visible="inactivo">
                        <i class="bi bi-play-fill"></i> Iniciar foco
                    </button>
                    <button type="button" class="btn btn-foco btn-lg-foco" data-p-accion="siguiente-foco" data-visible="libre">
                        <i class="bi bi-play-fill"></i> Iniciar siguiente foco
                    </button>
                    <button type="button" class="btn btn-foco btn-lg-foco" data-p-accion="pausar" data-visible="foco descanso" hidden>
                        <i class="bi bi-pause-fill"></i> Pausar
                    </button>
                    <button type="button" class="btn btn-foco btn-lg-foco" data-p-accion="reanudar" data-visible="foco-pausa descanso-pausa" hidden>
                        <i class="bi bi-play-fill"></i> Reanudar
                    </button>
                    <button type="button" class="btn btn-foco-suave" data-p-accion="saltar" data-visible="foco descanso foco-pausa descanso-pausa" hidden>
                        <i class="bi bi-skip-forward-fill"></i> Saltar fase
                    </button>
                    <button type="button" class="btn btn-foco-suave" data-p-accion="reiniciar" data-visible="foco descanso foco-pausa descanso-pausa" hidden>
                        <i class="bi bi-arrow-counterclockwise"></i> Reiniciar
                    </button>
                    <button type="button" class="btn btn-foco-peligro" data-p-accion="terminar" data-visible="foco descanso libre foco-pausa descanso-pausa" hidden>
                        <i class="bi bi-stop-fill"></i> Terminar sesión
                    </button>
                </div>

                <div class="aviso-foco mt-3 mb-0" data-p="terminada" role="status" hidden>
                    Sesión terminada. Podés verla en el <a href="{{ route('estudio.historial') }}">historial</a>.
                </div>
                <div class="pomodoro-errores mt-3" data-p="errores" role="alert" hidden></div>

                <hr class="my-4">

                <fieldset id="p-formulario" class="pomodoro-formulario">
                    <legend class="tarjeta-titulo">Configuración</legend>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="p-preset" class="form-label">Estilo</label>
                            <select id="p-preset" class="form-select">
                                @foreach ($estilos as $estilo)
                                    <option value="{{ $estilo->value }}">
                                        {{ $estilo->etiqueta() }}@if ($estilo->tiempos()) ({{ $estilo->tiempos()['foco'] }}/{{ $estilo->tiempos()['descanso'] }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="p-foco" class="form-label">Foco (min)</label>
                            <input id="p-foco" type="number" class="form-control" value="25" data-nombre="Foco"
                                   min="{{ $limites['foco'][0] }}" max="{{ $limites['foco'][1] }}" step="1" inputmode="numeric" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="p-descanso" class="form-label">Descanso (min)</label>
                            <input id="p-descanso" type="number" class="form-control" value="5" data-nombre="Descanso corto"
                                   min="{{ $limites['descanso'][0] }}" max="{{ $limites['descanso'][1] }}" step="1" inputmode="numeric" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="p-largo" class="form-label">Descanso largo</label>
                            <input id="p-largo" type="number" class="form-control" value="15" data-nombre="Descanso largo"
                                   min="{{ $limites['largo'][0] }}" max="{{ $limites['largo'][1] }}" step="1" inputmode="numeric" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="p-ciclos" class="form-label">Pomodoros por ciclo</label>
                            <input id="p-ciclos" type="number" class="form-control" value="4" data-nombre="Pomodoros por ciclo"
                                   min="{{ $limites['ciclos'][0] }}" max="{{ $limites['ciclos'][1] }}" step="1" inputmode="numeric" required>
                        </div>
                        <div class="col-12">
                            <div class="form-text mt-0">Foco de {{ $limites['foco'][0] }} a {{ $limites['foco'][1] }} min, descanso corto de {{ $limites['descanso'][0] }} a {{ $limites['descanso'][1] }}, largo de {{ $limites['largo'][0] }} a {{ $limites['largo'][1] }}. Los tiempos se guardan en este navegador.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="p-tarea" class="form-label">Tarea</label>
                            <select id="p-tarea" class="form-select">
                                <option value="">Sin tarea</option>
                                @foreach ($tareas as $tarea)
                                    <option value="{{ $tarea->id }}">{{ $tarea->titulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="p-contexto" class="form-label">Materia o tema</label>
                            <select id="p-contexto" class="form-select">
                                <option value="">Sin materia</option>
                                @foreach ($contextos as $id => $ruta)
                                    <option value="{{ $id }}">{{ $ruta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="p-tema" class="form-label">En qué voy a trabajar</label>
                            <input id="p-tema" type="text" class="form-control" maxlength="255" placeholder="Por ejemplo: ejercicios 4 al 8 de punteros" autocomplete="off">
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="d-flex flex-column gap-3 h-100">
                <div class="tarjeta tarjeta-relleno" data-p="resumen-sesion" hidden>
                    <h2 class="tarjeta-titulo">Esta sesión</h2>
                    <p class="fw-medium mb-3" data-p="detalle"></p>
                    <div class="row g-3">
                        <div class="col-6"><div class="metrica-etiqueta">Pomodoros completos</div><div class="metrica-valor" data-p="completados">0</div></div>
                        <div class="col-6"><div class="metrica-etiqueta">Interrumpidos</div><div class="metrica-valor" data-p="interrumpidos">0</div></div>
                        <div class="col-6"><div class="metrica-etiqueta">Descanso real</div><div class="metrica-valor" data-p="descanso">0 min</div></div>
                        <div class="col-6"><div class="metrica-etiqueta">Tiempo libre</div><div class="metrica-valor" data-p="libre">0 min</div></div>
                    </div>
                </div>

                <div class="tarjeta tarjeta-relleno">
                    <h2 class="tarjeta-titulo d-flex justify-content-between">Hoy <a href="{{ route('estudio.historial') }}" class="text-decoration-none text-lowercase fw-normal">historial</a></h2>
                    <div class="lista-fila"><span>Pomodoros completos</span><span class="lista-fila-meta">{{ $resumenHoy['pomodoros'] }}</span></div>
                    <div class="lista-fila"><span>Interrumpidos</span><span class="lista-fila-meta">{{ $resumenHoy['interrumpidos'] }}</span></div>
                    <div class="lista-fila"><span>Foco</span><span class="lista-fila-meta">{{ \App\Support\Duracion::formatear($resumenHoy['foco_seg'] / 60) }}</span></div>
                    <div class="lista-fila"><span>Descanso</span><span class="lista-fila-meta">{{ \App\Support\Duracion::formatear($resumenHoy['descanso_seg'] / 60) }}</span></div>
                    <div class="lista-fila"><span>Tiempo libre entre pomodoros</span><span class="lista-fila-meta">{{ \App\Support\Duracion::formatear($resumenHoy['libre_seg'] / 60) }}</span></div>
                </div>

                <div class="tarjeta tarjeta-relleno">
                    <h2 class="tarjeta-titulo">Avisos</h2>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="p-sonido" checked>
                        <label class="form-check-label" for="p-sonido">Sonido corto al terminar cada fase</label>
                    </div>
                    <button type="button" class="btn btn-foco-suave" data-p="notificar" hidden>
                        <i class="bi bi-bell"></i> Activar notificaciones del navegador
                    </button>
                    <p class="small text-secondary mt-3 mb-0">Cuando termina el foco empieza el descanso solo. Cuando termina el descanso, el tiempo libre corre hasta que inicies el siguiente foco. <a href="{{ route('estudio.metodos') }}">Ver formas de estudiar</a>.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
