@extends('layouts.app')

@section('titulo', 'Editar nota · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">Editar nota</h1>
    </div>

    <div class="tarjeta p-4 formulario-angosto">
        @include('notas._formulario', ['accion' => route('notas.update', $nota), 'metodo' => 'PUT'])
    </div>
@endsection
