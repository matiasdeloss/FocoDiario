@extends('layouts.app')

@section('titulo', 'Agenda · FocoDiario')

@push('head')
    @vite(['resources/css/agenda.css', 'resources/js/agenda-planner-grilla.js'])
@endpush

@section('contenido')
    <div class="agenda plan" data-actividades="{{ json_encode($actividades->mapWithKeys(fn ($a) => [$a->id => $a->colorActividad()?->clase()])->filter(), JSON_UNESCAPED_UNICODE) }}">
        <header class="plan-cab">
            <div class="plan-tarjeta plan-titulo-caja">
                <h1 class="plan-titulo">
                    <label class="visually-hidden" for="plan-titulo-campo">Título del planner (se guarda al salir del campo)</label>
                    <input type="text" id="plan-titulo-campo" class="plan-titulo-campo" maxlength="60" value="{{ $tituloPlanner }}"
                           placeholder="Planner semanal" autocomplete="off" spellcheck="false"
                           data-titulo-planner data-url="{{ route('agenda.titulo') }}" data-original="{{ $tituloPlanner }}">
                </h1>
                <nav class="plan-nav" aria-label="Cambiar de semana">
                    <a href="{{ $urlAnterior }}" class="btn btn-foco-suave btn-sm" aria-label="Semana anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                    <a href="{{ route('agenda.index') }}" class="btn btn-foco-suave btn-sm" @if ($esEstaSemana) aria-current="date" @endif>Esta semana</a>
                    <a href="{{ $urlSiguiente }}" class="btn btn-foco-suave btn-sm" aria-label="Semana siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                    <label class="plan-ir">
                        <span class="visually-hidden">Ir a la semana de una fecha</span>
                        <input type="date" class="plan-ir-campo" data-ir-fecha data-url="{{ route('agenda.index') }}" value="{{ $lunes->toDateString() }}" title="Ir a la semana de una fecha">
                    </label>
                    <form method="POST" action="{{ route('agenda.layout.restablecer', ['semana' => $lunes->toDateString()]) }}" data-tras-guardar>
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-foco-suave btn-sm" title="Volver a la disposición de fábrica en esta semana"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Restablecer disposición</button>
                    </form>
                </nav>
                @include('agenda._estado-guardado')
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

        {{-- Cada tarjeta (los siete días y Notas y Pendiente) es un elemento de GridStack: se mueve y se redimensiona como las cajas de la hoja del día. --}}
        <div class="plan-dias" data-plan-grilla data-url-layout="{{ route('agenda.layout', ['semana' => $lunes->toDateString()]) }}">
            @foreach ($dias as $dia)
                @php $pos = $layout[$dia['clave']]; @endphp
                <div class="grid-stack-item" gs-id="{{ $dia['clave'] }}" gs-x="{{ $pos['x'] }}" gs-y="{{ $pos['y'] }}" gs-w="{{ $pos['ancho'] }}" gs-h="{{ $pos['alto'] }}" gs-min-w="2" gs-min-h="4">
                    <div class="grid-stack-item-content">
                        <article class="plan-tarjeta plan-dia plan-dia-{{ $dia['clave'] }} {{ $dia['esHoy'] ? 'es-hoy' : '' }}">
                            <header class="plan-dia-cab">
                                <span class="plan-agarre" data-agarre role="button" tabindex="0" title="Arrastrar para mover"
                                      aria-label="Mover la tarjeta {{ $dia['nombre'] }}: con las flechas se mueve y con Mayús más flechas cambia el tamaño"><i class="bi bi-grip-vertical" aria-hidden="true"></i></span>
                                <h2 class="plan-dia-titulo">
                                    <a href="{{ route('agenda.dia', ['fecha' => $dia['fecha']->toDateString()]) }}" class="plan-dia-enlace"
                                       @if ($dia['esHoy']) aria-current="date" @endif>
                                        <span class="plan-dia-nombre">{{ $dia['nombre'] }}</span>
                                        <span class="plan-dia-numero">{{ $dia['numero'] }}</span>
                                        <span class="visually-hidden">: abrir la hoja del día{{ $dia['esHoy'] ? ' (hoy)' : '' }}</span>
                                    </a>
                                </h2>
                            </header>

                            {{-- El resto de la tarjeta lleva a la hoja del día (el encabezado queda libre para arrastrar). --}}
                            <a href="{{ route('agenda.dia', ['fecha' => $dia['fecha']->toDateString()]) }}" class="plan-dia-abrir" tabindex="-1" aria-hidden="true"></a>

                            @if ($dia['cajas']->isNotEmpty())
                                <ul class="plan-lineas" data-plan-lineas>
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
                                <a href="{{ route('agenda.dia', ['fecha' => $dia['fecha']->toDateString()]) }}" class="plan-mas" data-plan-mas hidden
                                   data-dia="{{ $dia['nombre'] }} {{ $dia['numero'] }}"></a>
                            @else
                                <p class="visually-hidden">Sin cajas este día.</p>
                            @endif
                        </article>
                    </div>
                </div>
            @endforeach

            @foreach ($zonas as $zona)
                @php $pos = $layout[$zona->value]; @endphp
                <div class="grid-stack-item" gs-id="{{ $zona->value }}" gs-x="{{ $pos['x'] }}" gs-y="{{ $pos['y'] }}" gs-w="{{ $pos['ancho'] }}" gs-h="{{ $pos['alto'] }}" gs-min-w="2" gs-min-h="4">
                    <div class="grid-stack-item-content">
                        <section class="plan-tarjeta plan-zona" aria-label="{{ $zona->etiqueta() }} de la semana">
                            @include('agenda._caja-semana', ['zona' => $zona, 'caja' => $semanales[$zona->value], 'lunes' => $lunes])
                        </section>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="visually-hidden" role="status" aria-live="polite" id="agenda-anuncio"></p>
    </div>

    <template id="agenda-plantilla-item">@include('agenda._item', ['item' => ['texto' => '', 'hecho' => false]])</template>
    @include('agenda._dialogo-actividades')
@endsection
