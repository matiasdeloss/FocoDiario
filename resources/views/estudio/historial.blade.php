@extends('layouts.app')

@section('titulo', 'Historial de estudio · FocoDiario')

@php
    use App\Enums\TipoIntervalo;
    use App\Support\Duracion;

    $hayFiltros = ! empty($filtros['desde']) || ! empty($filtros['hasta']) || ! empty($filtros['contexto_id']);
@endphp

@section('contenido')
    <div class="mb-3">
        <h1 class="pagina-titulo">Estudio</h1>
        <p class="text-secondary mb-0">Historial de sesiones con el descanso real y el tiempo libre entre pomodoros.</p>
    </div>

    @include('estudio._pestanas')

    <form method="GET" action="{{ route('estudio.historial') }}" class="tarjeta tarjeta-relleno mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-lg-3">
                <label for="filtro-desde" class="form-label">Desde</label>
                <input type="date" id="filtro-desde" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="form-control @error('desde') is-invalid @enderror">
                @error('desde')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-lg-3">
                <label for="filtro-hasta" class="form-label">Hasta</label>
                <input type="date" id="filtro-hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="form-control @error('hasta') is-invalid @enderror">
                @error('hasta')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-3">
                <label for="filtro-contexto" class="form-label">Materia o tema</label>
                <select id="filtro-contexto" name="contexto_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach ($contextos as $id => $ruta)
                        <option value="{{ $id }}" @selected((string) ($filtros['contexto_id'] ?? '') === (string) $id)>{{ $ruta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-foco-suave">Filtrar</button>
                @if ($hayFiltros)
                    <a href="{{ route('estudio.historial') }}" class="btn btn-foco-suave">Quitar filtros</a>
                @endif
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="tarjeta tarjeta-relleno tarjeta-resumen h-100"><div class="metrica-etiqueta">Pomodoros completos</div><div class="metrica-valor">{{ $totales['pomodoros'] }}</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tarjeta tarjeta-relleno tarjeta-resumen h-100"><div class="metrica-etiqueta">Tiempo de foco</div><div class="metrica-valor">{{ Duracion::formatearSegundos($totales['foco_seg']) }}</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tarjeta tarjeta-relleno tarjeta-resumen h-100"><div class="metrica-etiqueta">Tiempo de descanso</div><div class="metrica-valor">{{ Duracion::formatearSegundos($totales['descanso_seg']) }}</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tarjeta tarjeta-relleno tarjeta-resumen h-100"><div class="metrica-etiqueta">Tiempo libre</div><div class="metrica-valor">{{ Duracion::formatearSegundos($totales['libre_seg']) }}</div></div>
        </div>
    </div>
    <p class="small text-secondary">Totales {{ $hayFiltros ? 'de los filtros aplicados' : 'de todo el historial' }}, {{ $totales['interrumpidos'] }} {{ $totales['interrumpidos'] === 1 ? 'foco interrumpido' : 'focos interrumpidos' }}.</p>

    @forelse ($dias as $dia => $sesionesDelDia)
        @php
            $fecha = \Carbon\CarbonImmutable::parse($dia);
            $t = $totalesPorDia[$dia];
        @endphp
        <section class="tarjeta mb-3" aria-labelledby="dia-{{ $dia }}">
            <div class="px-4 pt-4 pb-2 d-flex flex-wrap justify-content-between align-items-baseline gap-2">
                <h2 id="dia-{{ $dia }}" class="h5 mb-0">{{ $fecha->translatedFormat('l j \d\e F') }}</h2>
                <span class="small text-secondary">
                    {{ $t['pomodoros'] }} {{ $t['pomodoros'] === 1 ? 'pomodoro' : 'pomodoros' }} ·
                    {{ Duracion::formatearSegundos($t['foco_seg']) }} de foco ·
                    {{ Duracion::formatearSegundos($t['descanso_seg']) }} de descanso
                </span>
            </div>
            <div class="tabla-foco-contenedor">
                <table class="table tabla-foco">
                    <thead>
                        <tr>
                            <th scope="col">Hora</th>
                            <th scope="col">Trabajo</th>
                            <th scope="col">Estilo</th>
                            <th scope="col">Foco previsto / real</th>
                            <th scope="col">Pomodoros</th>
                            <th scope="col">Descanso real</th>
                            <th scope="col">Tiempo libre</th>
                            <th scope="col"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sesionesDelDia as $sesion)
                            @php
                                $focoPrevisto = $sesion->segundosPlanificadosDe(TipoIntervalo::Foco);
                                $focoReal = $sesion->segundosDe(TipoIntervalo::Foco);
                                $descansoPrevisto = $sesion->segundosPlanificadosDe(TipoIntervalo::Descanso);
                                $descansoReal = $sesion->segundosDe(TipoIntervalo::Descanso);
                            @endphp
                            <tr>
                                <td class="text-nowrap">
                                    {{ $sesion->iniciada_en->format('H:i') }}
                                    @if ($sesion->estado === \App\Enums\EstadoSesion::EnCurso)
                                        <div><span class="badge-foco {{ $sesion->estado->claseBadge() }}">{{ $sesion->estado->etiqueta() }}</span></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-medium">{{ $sesion->tema ?: ($sesion->tarea?->titulo ?? 'Sin detalle') }}</div>
                                    @if ($sesion->tema && $sesion->tarea)
                                        <div class="small text-secondary">{{ $sesion->tarea->titulo }}</div>
                                    @endif
                                    @if ($sesion->contexto)
                                        <div class="small text-secondary">{{ $sesion->contexto->rutaCompleta() }}</div>
                                    @endif
                                </td>
                                <td class="text-nowrap">{{ $sesion->estilo->etiqueta() }}<div class="small text-secondary">{{ Duracion::formatearSegundos($sesion->foco_seg) }} / {{ Duracion::formatearSegundos($sesion->descanso_seg) }}</div></td>
                                <td class="text-nowrap">{{ Duracion::formatearSegundos($focoPrevisto) }} / {{ Duracion::formatearSegundos($focoReal) }}</td>
                                <td class="text-nowrap">
                                    {{ $sesion->pomodorosCompletados() }} completos
                                    @if ($sesion->pomodorosInterrumpidos() > 0)
                                        <div class="small text-secondary">{{ $sesion->pomodorosInterrumpidos() }} {{ $sesion->pomodorosInterrumpidos() === 1 ? 'interrumpido' : 'interrumpidos' }}</div>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    {{ Duracion::formatearSegundos($descansoReal) }}
                                    @if ($descansoPrevisto > 0)
                                        <div class="small text-secondary">de {{ Duracion::formatearSegundos($descansoPrevisto) }} previstos</div>
                                    @endif
                                </td>
                                <td class="text-nowrap">{{ Duracion::formatearSegundos($sesion->segundosDe(TipoIntervalo::Libre)) }}</td>
                                <td class="text-end text-nowrap">
                                    <form method="POST" action="{{ route('estudio.sesiones.destroy', $sesion) }}" class="d-inline"
                                          hx-delete="{{ route('estudio.sesiones.destroy', $sesion) }}" hx-swap="none"
                                          hx-confirm="¿Eliminar esta sesión y sus bloques de tiempo del registro?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icono" title="Eliminar sesión" aria-label="Eliminar la sesión de las {{ $sesion->iniciada_en->format('H:i') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="tarjeta">
            <p class="estado-vacio px-4">
                @if ($hayFiltros)
                    Ninguna sesión coincide con los filtros.
                @else
                    Todavía no hay sesiones. Iniciá un foco en el <a href="{{ route('estudio.index') }}">temporizador</a> y aparece acá.
                @endif
            </p>
        </div>
    @endforelse

    @if ($sesiones->hasPages())
        <div class="mt-3">{{ $sesiones->links('pagination::bootstrap-5') }}</div>
    @endif
@endsection
