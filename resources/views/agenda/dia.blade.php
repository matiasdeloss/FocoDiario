@extends('layouts.app')

@section('titulo', 'Hoja del '.$dia->translatedFormat('j \d\e F').' · FocoDiario')

@push('head')
    @vite(['resources/css/agenda.css', 'resources/js/agenda-dia.js'])
@endpush

@section('contenido')
    <div class="agenda hoja" data-actividades="{{ json_encode($actividades->mapWithKeys(fn ($a) => [$a->id => $a->colorActividad()?->clase()])->filter(), JSON_UNESCAPED_UNICODE) }}">
        <header class="hoja-cab">
            <div class="hoja-izquierda">
                <a href="{{ route('agenda.index', ['semana' => $lunes->toDateString()]) }}" class="btn btn-foco-suave hoja-suave"><i class="bi bi-arrow-left" aria-hidden="true"></i> Planner semanal</a>
                <div class="hoja-titulos">
                    <span class="hoja-kicker">Hoja del día{{ $esHoy ? ' · hoy' : '' }}</span>
                    <h1 class="hoja-titulo">{{ ucfirst($dia->translatedFormat('l j \d\e F')) }}</h1>
                </div>
            </div>

            <div class="hoja-derecha">
                <div class="hoja-barra" role="toolbar" aria-label="Herramientas de la hoja">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="ver-hechas" data-interruptor="hechas" checked>
                        <label class="form-check-label" for="ver-hechas">Mostrar hechas</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="ver-tildadas" data-interruptor="tildadas" checked>
                        <label class="form-check-label" for="ver-tildadas">Mostrar tildadas</label>
                    </div>
                    <button type="button" class="btn btn-foco-suave hoja-suave" data-abrir-dialogo="dialogo-actividades"><i class="bi bi-palette" aria-hidden="true"></i> Actividades</button>
                    <button type="button" class="btn btn-foco" data-nueva-caja><i class="bi bi-plus-lg" aria-hidden="true"></i> Nueva caja</button>
                    @include('agenda._estado-guardado')
                </div>
                <nav class="hoja-dias" aria-label="Cambiar de día">
                    <a href="{{ $urlAnterior }}" class="btn btn-foco-suave hoja-suave" aria-label="Día anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                    <a href="{{ route('agenda.dia', ['fecha' => today()->toDateString()]) }}" class="btn btn-foco-suave hoja-suave" @if ($esHoy) aria-current="date" @endif>Hoy</a>
                    <a href="{{ $urlSiguiente }}" class="btn btn-foco-suave hoja-suave" aria-label="Día siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                </nav>
            </div>
        </header>

        <div class="lienzo" data-lienzo data-fecha="{{ $dia->toDateString() }}"
             data-url-layout="{{ route('agenda.dia.layout', ['fecha' => $dia->toDateString()]) }}" data-url-cajas="{{ route('agenda.cajas.store') }}">
            <p class="lienzo-vacio" data-vacio @if ($cajas->isNotEmpty()) hidden @endif>Esta hoja está vacía. Creá una caja con "Nueva caja" y acomodala como quieras.</p>
            <div class="lienzo-cajas" data-cajas>
                @foreach ($cajas as $caja)
                    @include('agenda._caja', ['caja' => $caja])
                @endforeach
            </div>
        </div>

        <p class="visually-hidden" role="status" aria-live="polite" id="agenda-anuncio"></p>
    </div>

    <template id="agenda-plantilla-item">@include('agenda._item', ['item' => ['texto' => '', 'hecho' => false]])</template>
    @include('agenda._dialogo-caja')
    @include('agenda._dialogo-actividades')
@endsection
