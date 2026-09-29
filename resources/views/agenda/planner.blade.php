@extends('layouts.app')

@section('titulo', 'Agenda · FocoDiario')

@push('head')
    @vite(['resources/css/agenda.css', 'resources/js/agenda.js'])
@endpush

@section('contenido')
    <div class="agenda plan" data-actividades="{{ json_encode($actividades->mapWithKeys(fn ($a) => [$a->id => $a->colorActividad()?->clase()])->filter(), JSON_UNESCAPED_UNICODE) }}">
        <header class="plan-cab">
            <div class="plan-tarjeta plan-titulo-caja">
                <h1 class="plan-titulo">Planner semanal</h1>
                <nav class="plan-nav" aria-label="Cambiar de semana">
                    <a href="{{ $urlAnterior }}" class="btn btn-foco-suave btn-sm" aria-label="Semana anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                    <a href="{{ route('agenda.index') }}" class="btn btn-foco-suave btn-sm" @if ($esEstaSemana) aria-current="date" @endif>Esta semana</a>
                    <a href="{{ $urlSiguiente }}" class="btn btn-foco-suave btn-sm" aria-label="Semana siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                    <label class="plan-ir">
                        <span class="visually-hidden">Ir a la semana de una fecha</span>
                        <input type="date" class="plan-ir-campo" data-ir-fecha data-url="{{ route('agenda.index') }}" value="{{ $lunes->toDateString() }}" title="Ir a la semana de una fecha">
                    </label>
                </nav>
            </div>

            <div class="plan-tarjeta plan-info-caja">
                <dl class="plan-datos">
                    <div><dt>Semana</dt><dd>{{ $etiquetaSemana }}</dd></div>
                    <div><dt>Mes</dt><dd>{{ $etiquetaMes }}</dd></div>
                </dl>
                <div class="plan-leyenda">
                    <ul class="plan-leyenda-lista" aria-label="Actividades y sus colores">
                        @forelse ($actividades as $actividad)
                            <li class="plan-chip {{ $actividad->colorActividad()?->clase() }}"><span class="paleta-punto" aria-hidden="true"></span>{{ $actividad->nombre }}</li>
                        @empty
                            <li class="plan-leyenda-vacia">Creá tus actividades para darle color a las cajas.</li>
                        @endforelse
                    </ul>
                    <button type="button" class="btn btn-foco-suave btn-sm" data-abrir-dialogo="dialogo-actividades"><i class="bi bi-palette" aria-hidden="true"></i> Actividades</button>
                </div>
            </div>
        </header>

        <div class="plan-dias">
            @foreach ($dias as $dia)
                <article class="plan-tarjeta plan-dia plan-dia-{{ $dia['clave'] }} {{ $dia['esHoy'] ? 'es-hoy' : '' }}">
                    <header class="plan-dia-cab">
                        <h2 class="plan-dia-titulo">
                            <a href="{{ route('agenda.dia', ['fecha' => $dia['fecha']->toDateString()]) }}" class="plan-dia-enlace"
                               @if ($dia['esHoy']) aria-current="date" @endif>
                                {{ $dia['nombre'] }}
                                <span class="plan-dia-numero">{{ $dia['numero'] }}</span>
                                <span class="visually-hidden">: abrir la hoja del día{{ $dia['esHoy'] ? ' (hoy)' : '' }}</span>
                            </a>
                        </h2>
                    </header>

                    @if ($dia['cajas']->isNotEmpty())
                        <ul class="plan-lineas">
                            @foreach ($dia['cajas'] as $caja)
                                @php $items = $caja->tipo === \App\Enums\TipoCaja::Lista ? collect($caja->itemsLista())->filter(fn ($i) => trim($i['texto']) !== '') : collect(); @endphp
                                <li class="plan-linea {{ $caja->actividad?->colorActividad()?->clase() }} {{ $caja->hecha ? 'es-hecha' : '' }}">
                                    @if ($caja->hora_inicio)
                                        <span class="plan-hora">{{ $caja->horaTexto() }}</span>
                                    @endif
                                    <span class="plan-linea-titulo">{{ $caja->tituloVisible() }}</span>
                                    @if ($items->isNotEmpty())
                                        <span class="plan-cuenta" title="Ítems tildados">{{ $items->where('hecho', true)->count() }}/{{ $items->count() }}</span>
                                    @endif
                                    @if ($caja->hecha)
                                        <span class="visually-hidden">(hecha)</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="visually-hidden">Sin cajas este día.</p>
                    @endif
                </article>
            @endforeach
        </div>

        <section class="plan-tarjeta plan-notas" aria-label="Notas y pendientes de la semana">
            <div class="plan-notas-columnas">
                @foreach ($zonas as $zona)
                    @include('agenda._caja-semana', ['zona' => $zona, 'caja' => $semanales[$zona->value], 'lunes' => $lunes])
                @endforeach
            </div>
            <div class="plan-notas-pie">@include('agenda._estado-guardado')</div>
        </section>
    </div>

    <template id="agenda-plantilla-item">@include('agenda._item', ['item' => ['texto' => '', 'hecho' => false]])</template>
    @include('agenda._dialogo-actividades')
@endsection
