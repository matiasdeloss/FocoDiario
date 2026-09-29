@extends('layouts.app')

@section('titulo', 'Editar recordatorio · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">Editar recordatorio</h1>
    </div>

    <div class="tarjeta tarjeta-relleno formulario-angosto">
        @include('recordatorios._formulario', ['accion' => route('recordatorios.update', $recordatorio), 'metodo' => 'PUT'])
    </div>
@endsection
