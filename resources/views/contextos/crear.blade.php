@extends('layouts.app')

@section('titulo', 'Nuevo contexto · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">Nuevo contexto</h1>
    </div>

    <div class="tarjeta p-4 formulario-angosto">
        @include('contextos._formulario', ['accion' => route('contextos.store'), 'metodo' => 'POST'])
    </div>
@endsection
