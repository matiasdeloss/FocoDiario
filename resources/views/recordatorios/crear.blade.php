@extends('layouts.app')

@section('titulo', 'Nuevo recordatorio · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">Nuevo recordatorio</h1>
    </div>

    <div class="tarjeta p-4 formulario-angosto">
        @include('recordatorios._formulario', ['accion' => route('recordatorios.store'), 'metodo' => 'POST'])
    </div>
@endsection
