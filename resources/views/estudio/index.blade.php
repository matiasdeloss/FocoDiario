@extends('layouts.app')

@section('titulo', 'Estudio · FocoDiario')

@push('head')
    @vite('resources/css/estudio.css')
@endpush

@section('contenido')
    <div class="mb-3">
        <h1 class="pagina-titulo">Estudio</h1>
        <p class="text-secondary mb-0">Foco y descanso con registro del tiempo real, incluido el tiempo libre entre pomodoros.</p>
    </div>

    @include('estudio._pestanas')

    <div id="pomodoro" class="estudio-fila"
         data-fase="inactivo"
         data-url-sesiones="{{ route('estudio.sesiones.store') }}"
         data-url-historial="{{ route('estudio.historial') }}"
         data-sesion-activa="{{ $sesionActivaId }}"
         data-presets='@json($presets)'>

        <section class="tarjeta estudio-timer pomodoro-principal" aria-label="Temporizador">
                <div class="estudio-timer-cabecera">
                    <span class="badge-foco pomodoro-fase" data-p="fase">Listo para empezar</span>
                    <span class="lista-fila-meta" data-p="ciclo"></span>
                </div>

                <div class="estudio-timer-centro">
                    <div class="pomodoro-anillo">
                        <svg class="pomodoro-anillo-svg" viewBox="0 0 320 320" aria-hidden="true" focusable="false">
                            <circle class="pomodoro-anillo-pista" cx="160" cy="160" r="148" fill="none" pathLength="1"></circle>
                            <circle class="pomodoro-anillo-arco" data-p="anillo-arco" cx="160" cy="160" r="148" fill="none" pathLength="1"
                                    transform="rotate(-90 160 160)"></circle>
                        </svg>
                        <div class="pomodoro-anillo-contenido">
                            <button type="button" class="pomodoro-tiempo pomodoro-tiempo-grande" data-p="tiempo" data-tamano="normal" aria-label="Editar tiempo, 25:00">25:00</button>
                            <div class="pomodoro-puntos" data-p="puntos" aria-hidden="true"></div>
                        </div>
                    </div>
                    <span class="visually-hidden" role="status" aria-live="polite" data-p="anuncio"></span>
                    <p class="text-secondary mb-0 estudio-timer-ayuda" data-p="ayuda" aria-live="polite"></p>
                </div>

                <div class="estudio-timer-controles">
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

                <div class="pomodoro-errores mt-3" data-p="errores" role="alert" hidden></div>
        </section>

        <section class="tarjeta estudio-config" aria-labelledby="estudio-config-titulo">
                <fieldset id="p-formulario" class="pomodoro-formulario">
                    <legend id="estudio-config-titulo" class="tarjeta-titulo">Configuración</legend>

                    <div class="config-campos">
                        <div class="config-campo">
                            <label for="p-preset" class="config-etiqueta">Estilo</label>
                            <select id="p-preset" class="form-select">
                                @foreach ($estilos as $estilo)
                                    <option value="{{ $estilo->value }}">
                                        {{ $estilo->etiqueta() }}@if ($estilo->tiempos()) ({{ intdiv($estilo->tiempos()['foco'], 60) }}/{{ intdiv($estilo->tiempos()['descanso'], 60) }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="config-tiempos">
                        @foreach ([
                            ['clave' => 'foco', 'titulo' => 'Foco', 'nombre' => 'Foco', 'defecto' => 1500],
                            ['clave' => 'descanso', 'titulo' => 'Descanso corto', 'nombre' => 'Descanso corto', 'defecto' => 300],
                            ['clave' => 'largo', 'titulo' => 'Descanso largo', 'nombre' => 'Descanso largo', 'defecto' => 900],
                        ] as $campo)
                            <div class="pomodoro-duracion" role="group" aria-labelledby="p-{{ $campo['clave'] }}-etq" data-duracion="{{ $campo['clave'] }}" data-nombre="{{ $campo['nombre'] }}">
                                <span id="p-{{ $campo['clave'] }}-etq" class="config-etiqueta">{{ $campo['titulo'] }}</span>
                                <div class="pomodoro-duracion-campos">
                                    <input id="p-{{ $campo['clave'] }}-min" type="number" class="form-control" aria-label="{{ $campo['titulo'] }}, minutos"
                                           value="{{ intdiv($campo['defecto'], 60) }}" min="0" max="{{ intdiv($limites[$campo['clave']][1], 60) }}" step="1" inputmode="numeric" required>
                                    <span class="mmss-unidad" aria-hidden="true">min</span>
                                    <span class="mmss-sep" aria-hidden="true">:</span>
                                    <input id="p-{{ $campo['clave'] }}-seg" type="number" class="form-control" aria-label="{{ $campo['titulo'] }}, segundos"
                                           value="{{ $campo['defecto'] % 60 }}" min="0" max="59" step="1" inputmode="numeric" required>
                                    <span class="mmss-unidad" aria-hidden="true">seg</span>
                                </div>
                            </div>
                        @endforeach

                            <div class="config-fila">
                                <label for="p-ciclos" class="config-etiqueta">Pomodoros por ciclo</label>
                                <div class="config-stepper">
                                    <button type="button" class="stepper-boton" data-paso="-1" aria-label="Un pomodoro menos por ciclo"><i class="bi bi-dash-lg"></i></button>
                                    <input id="p-ciclos" type="number" class="form-control" value="4" data-nombre="Pomodoros por ciclo"
                                           min="{{ $limites['ciclos'][0] }}" max="{{ $limites['ciclos'][1] }}" step="1" inputmode="numeric" required>
                                    <button type="button" class="stepper-boton" data-paso="1" aria-label="Un pomodoro más por ciclo"><i class="bi bi-plus-lg"></i></button>
                                </div>
                            </div>
                            <p class="config-ayuda pomodoro-ayuda-tiempos">Cada tiempo va de 00:05 a 180:00. Se guardan en este navegador.</p>
                        </div>

                        <div class="config-campo">
                            <label for="p-tarea" class="config-etiqueta">Tarea</label>
                            <select id="p-tarea" class="form-select">
                                <option value="">Sin tarea</option>
                                @foreach ($tareas as $tarea)
                                    <option value="{{ $tarea->id }}">{{ $tarea->titulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="config-campo">
                            <label for="p-contexto" class="config-etiqueta">Materia o tema</label>
                            <select id="p-contexto" class="form-select">
                                <option value="">Sin materia</option>
                                @foreach ($contextos as $id => $ruta)
                                    <option value="{{ $id }}">{{ $ruta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="config-campo">
                            <label for="p-tema" class="config-etiqueta">En qué voy a trabajar</label>
                            <input id="p-tema" type="text" class="form-control" maxlength="255" placeholder="Por ejemplo: ejercicios 4 al 8 de punteros" autocomplete="off">
                        </div>
                    </div>
                </fieldset>
        </section>

        <div class="estudio-lateral">
                {{-- Siempre presente (atenuada sin sesión): si apareciera al iniciar, empujaría hacia abajo las demás tarjetas. --}}
                <div class="tarjeta tarjeta-relleno estudio-sesion es-inactiva" data-p="resumen-sesion">
                    <h2 class="tarjeta-titulo">Esta sesión</h2>
                    <p class="fw-medium mb-3" data-p="detalle">Todavía no empezaste una sesión.</p>
                    <div class="row g-3">
                        <div class="col-6"><div class="metrica-etiqueta">Pomodoros completos</div><div class="metrica-valor" data-p="completados">0</div></div>
                        <div class="col-6"><div class="metrica-etiqueta">Interrumpidos</div><div class="metrica-valor" data-p="interrumpidos">0</div></div>
                        <div class="col-6"><div class="metrica-etiqueta">Descanso real</div><div class="metrica-valor" data-p="descanso">0 s</div></div>
                        <div class="col-6"><div class="metrica-etiqueta">Tiempo libre</div><div class="metrica-valor" data-p="libre">0 s</div></div>
                    </div>
                </div>

                <div class="tarjeta tarjeta-relleno">
                    <h2 class="tarjeta-titulo">Avisos</h2>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="p-sonido" checked>
                        <label class="form-check-label" for="p-sonido">Sonido al terminar cada fase</label>
                    </div>
                    <div class="sonidos" data-p="sonidos">
                        <label for="p-sonido-tipo" class="sonidos-leyenda">Elegí el sonido</label>
                        <div class="sonido-selector-fila">
                            <select id="p-sonido-tipo" class="form-select" aria-label="Elegí el sonido">
                                @foreach ([
                                    ['campana', 'Campana', 'Dos toques suaves y breves'],
                                    ['suave', 'Suave', 'Tres notas ascendentes, tranquilas'],
                                    ['alarma', 'Alarma', 'Pitidos repetidos, imposible de ignorar'],
                                    ['digital', 'Digital', 'Doble pitido de reloj digital'],
                                    ['marimba', 'Marimba', 'Secuencia cálida y melodiosa'],
                                ] as [$clave, $nombre, $descripcion])
                                    <option value="{{ $clave }}" data-desc="{{ $descripcion }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn-sonido-probar" data-p-probar aria-label="Probar sonido seleccionado">
                                <i class="bi bi-play-fill" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p class="config-ayuda sonido-desc" data-p="sonido-desc" aria-live="polite"></p>
                    </div>
                    <button type="button" class="btn btn-foco-suave" data-p="notificar" hidden>
                        <i class="bi bi-bell"></i> Activar notificaciones del navegador
                    </button>
                    <p class="small text-secondary mt-3 mb-0">Cuando termina el foco empieza el descanso solo. Cuando termina el descanso, el tiempo libre corre hasta que inicies el siguiente foco. <a href="{{ route('estudio.metodos') }}">Ver formas de estudiar</a>.</p>
                </div>

                <div class="tarjeta tarjeta-relleno">
                    <h2 class="tarjeta-titulo d-flex justify-content-between">Hoy <a href="{{ route('estudio.historial') }}" class="text-decoration-none text-lowercase fw-normal">historial</a></h2>
                    <div class="lista-fila"><span>Pomodoros completos</span><span class="lista-fila-meta">{{ $resumenHoy['pomodoros'] }}</span></div>
                    <div class="lista-fila"><span>Interrumpidos</span><span class="lista-fila-meta">{{ $resumenHoy['interrumpidos'] }}</span></div>
                    <div class="lista-fila"><span>Foco</span><span class="lista-fila-meta">{{ \App\Support\Duracion::formatearSegundos($resumenHoy['foco_seg']) }}</span></div>
                    <div class="lista-fila"><span>Descanso</span><span class="lista-fila-meta">{{ \App\Support\Duracion::formatearSegundos($resumenHoy['descanso_seg']) }}</span></div>
                    <div class="lista-fila"><span>Tiempo libre entre pomodoros</span><span class="lista-fila-meta">{{ \App\Support\Duracion::formatearSegundos($resumenHoy['libre_seg']) }}</span></div>
                </div>
        </div>
    </div>

    @include('estudio._historial')
@endsection
