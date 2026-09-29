@php
    use App\Enums\EstadoSesion;
    use App\Enums\TipoIntervalo;
    use App\Support\Duracion;

    $urlRango = fn (string $r) => route('estudio.index', array_filter(['rango' => $r, 'materia' => $hMateria])).'#historial';
    $maxMin = max(1, collect($hBarras)->max('min'));
    $totalMin = intdiv($hTotales['foco_seg'], 60);
    $etiquetaRango = $hRango === 'mes' ? 'Este mes' : 'Esta semana';
    $denso = count($hBarras) > 10;
@endphp

<section id="historial" class="tarjeta estudio-historial" aria-labelledby="historial-titulo">
    <header class="historial-cabecera">
        <h2 id="historial-titulo" class="tarjeta-titulo mb-0">Historial</h2>

        <form method="GET" action="{{ route('estudio.index') }}#historial" class="historial-filtros">
            <div class="historial-rango" role="group" aria-label="Rango del historial">
                <a href="{{ $urlRango('semana') }}" class="{{ $hRango === 'semana' ? 'activa' : '' }}" @if ($hRango === 'semana') aria-current="true" @endif>Esta semana</a>
                <a href="{{ $urlRango('mes') }}" class="{{ $hRango === 'mes' ? 'activa' : '' }}" @if ($hRango === 'mes') aria-current="true" @endif>Este mes</a>
            </div>
            <input type="hidden" name="rango" value="{{ $hRango }}">
            <label for="historial-materia" class="visually-hidden">Filtrar por materia</label>
            <select id="historial-materia" name="materia" class="form-select" onchange="this.form.submit()">
                <option value="">Todas las materias</option>
                @foreach ($contextos as $id => $ruta)
                    <option value="{{ $id }}" @selected($hMateria === $id)>{{ $ruta }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn btn-foco-suave">Filtrar</button></noscript>
            <a href="{{ route('estudio.historial') }}" class="historial-todo">Ver todo</a>
        </form>
    </header>

    <dl class="historial-stats">
        <div><dt>Foco</dt><dd>{{ Duracion::formatearSegundos($hTotales['foco_seg']) }}</dd></div>
        <div><dt>Pomodoros</dt><dd>{{ $hTotales['pomodoros'] }}</dd></div>
        <div><dt>Sesiones</dt><dd>{{ $hSesiones }}</dd></div>
        <div><dt>Racha</dt><dd>{{ $hRacha }} {{ $hRacha === 1 ? 'día' : 'días' }}</dd></div>
    </dl>

    @if ($hSesiones === 0)
        <div class="historial-vacio">
            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
            <p class="mb-0">
                @if ($hMateria)
                    Sin sesiones de esta materia {{ $hRango === 'mes' ? 'este mes' : 'esta semana' }}.
                @else
                    {{ $etiquetaRango }} todavía no hay sesiones. Iniciá un foco y aparece acá.
                @endif
            </p>
        </div>
    @else
        <figure class="historial-grafico" aria-label="Minutos de foco por día. {{ $etiquetaRango }}: {{ $totalMin }} min en total.">
            <div class="historial-barras {{ $denso ? 'es-denso' : '' }}" role="list">
                @foreach ($hBarras as $b)
                    <div class="historial-barra {{ $b['futuro'] ? 'es-futuro' : '' }}" role="listitem"
                         title="{{ $b['fecha']->translatedFormat('l j') }}: {{ $b['min'] }} min"
                         aria-label="{{ $b['fecha']->translatedFormat('l j') }}: {{ $b['min'] }} min de foco">
                        <span class="historial-barra-valor">{{ $b['min'] > 0 ? $b['min'] : '' }}</span>
                        <span class="historial-barra-col"><span style="height: {{ $b['min'] > 0 ? max(4, round($b['min'] / $maxMin * 100)) : 0 }}%"></span></span>
                        <span class="historial-barra-dia">{{ $denso ? $b['fecha']->format('j') : mb_substr($b['fecha']->translatedFormat('D'), 0, 3) }}</span>
                    </div>
                @endforeach
            </div>
        </figure>

        <div class="historial-dias">
            @foreach ($hDias as $dia => $sesionesDelDia)
                @php
                    $fecha = \Carbon\CarbonImmutable::parse($dia);
                    $focoDia = $sesionesDelDia->sum(fn ($s) => $s->segundosDe(TipoIntervalo::Foco));
                @endphp
                <section class="historial-dia" aria-labelledby="hd-{{ $dia }}">
                    <h3 id="hd-{{ $dia }}" class="historial-dia-titulo">
                        <span>{{ $fecha->isToday() ? 'Hoy' : ($fecha->isYesterday() ? 'Ayer' : ucfirst($fecha->translatedFormat('l j \d\e F'))) }}</span>
                        <span class="historial-dia-total">{{ Duracion::formatearSegundos($focoDia) }}</span>
                    </h3>
                    <ul class="historial-lista">
                        @foreach ($sesionesDelDia as $sesion)
                            @php
                                $ok = $sesion->pomodorosCompletados();
                                $mal = $sesion->pomodorosInterrumpidos();
                                $color = $sesion->contexto?->colorActividad();
                                $titulo = $sesion->tema ?: ($sesion->tarea?->titulo ?? 'Sin detalle');
                            @endphp
                            <li class="historial-sesion">
                                <time class="hs-hora" datetime="{{ $sesion->iniciada_en->format('Y-m-d H:i') }}">{{ $sesion->iniciada_en->format('H:i') }}</time>
                                <div class="hs-trabajo">
                                    <div class="hs-titulo">{{ $titulo }}</div>
                                    @if ($sesion->contexto)
                                        <div class="hs-materia" @if ($color) style="--mat: var(--actividad-{{ $color->clave() }}-acento)" @endif>
                                            <span class="hs-punto" aria-hidden="true"></span>{{ $sesion->contexto->rutaCompleta() }}
                                        </div>
                                    @endif
                                </div>
                                <span class="hs-estilo">{{ $sesion->estilo->etiqueta() }}</span>
                                <span class="hs-duracion">{{ Duracion::formatearSegundos($sesion->segundosDe(TipoIntervalo::Foco)) }}</span>
                                <span class="hs-puntos" role="img" aria-label="{{ $ok }} {{ $ok === 1 ? 'pomodoro completo' : 'pomodoros completos' }}{{ $mal ? ', '.$mal.' interrumpido'.($mal === 1 ? '' : 's') : '' }}">
                                    @for ($i = 0; $i < min($ok, 8); $i++)<i class="lleno"></i>@endfor
                                    @for ($i = 0; $i < min($mal, max(0, 8 - $ok)); $i++)<i class="roto"></i>@endfor
                                    @if ($ok + $mal > 8)<small>+{{ $ok + $mal - 8 }}</small>@endif
                                    @if ($ok + $mal === 0)<small>-</small>@endif
                                </span>
                                <span class="hs-acciones">
                                    @if ($sesion->estado === EstadoSesion::EnCurso)
                                        <span class="badge-foco {{ $sesion->estado->claseBadge() }}">{{ $sesion->estado->etiqueta() }}</span>
                                    @endif
                                    <form method="POST" action="{{ route('estudio.sesiones.destroy', $sesion) }}" class="d-inline"
                                          hx-delete="{{ route('estudio.sesiones.destroy', $sesion) }}" hx-swap="none"
                                          hx-confirm="¿Eliminar esta sesión y sus bloques de tiempo del registro?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icono" title="Eliminar sesión" aria-label="Eliminar la sesión de las {{ $sesion->iniciada_en->format('H:i') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</section>
