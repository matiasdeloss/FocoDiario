@extends('layouts.app')

@section('titulo', 'Hoy · FocoDiario')

@push('head')
    @vite(['resources/css/hoy.css', 'resources/js/hoy.js'])
@endpush

@section('contenido')
    <div class="hoy">
        <header class="hoy-encabezado">
            <div class="hoy-saludo">
                <span class="hoy-kicker">{{ $diaSemana }}</span>
                <h1 class="hoy-titulo">{{ $saludo }}</h1>
            </div>
            <div class="hoy-fecha">
                <span class="hoy-fecha-larga">{{ $fechaLarga }}</span>
            </div>
        </header>

        <div class="hoy-cuerpo">
            <div class="hoy-col hoy-col-principal">
                @include('hoy._nota-rapida')
                @include('hoy._semana')
                @include('hoy._recomendaciones')
            </div>

            <div class="hoy-col hoy-col-lateral">
                @include('hoy._recordatorios')
                @include('hoy._tareas')
                @include('hoy._pomodoro')
            </div>
        </div>
    </div>
@endsection
