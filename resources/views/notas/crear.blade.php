@extends('layouts.app')

@section('titulo', 'Nueva nota · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">Nueva nota</h1>
    </div>

    <div class="tarjeta p-4 formulario-angosto">
        @include('notas._formulario', ['accion' => route('notas.store'), 'metodo' => 'POST'])
    </div>
@endsection
