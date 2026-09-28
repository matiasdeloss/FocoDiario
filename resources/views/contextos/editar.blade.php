@extends('layouts.app')

@section('titulo', 'Editar contexto · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">Editar contexto</h1>
    </div>

    <div class="tarjeta p-4 formulario-angosto">
        @include('contextos._formulario', ['accion' => route('contextos.update', $contexto), 'metodo' => 'PUT'])
    </div>
@endsection
