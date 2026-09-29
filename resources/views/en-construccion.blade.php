@extends('layouts.app')

@section('titulo', $modulo . ' · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">{{ $modulo }}</h1>
    </div>

    <div class="tarjeta tarjeta-relleno">
        <p class="estado-vacio mb-0">Este módulo todavía está en construcción.</p>
    </div>
@endsection
