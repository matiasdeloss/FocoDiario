@extends('layouts.app')

@section('titulo', 'Editar tarea · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">Editar tarea</h1>
    </div>

    <div class="tarjeta tarjeta-relleno formulario-angosto">
        @include('tareas._formulario', ['accion' => route('tareas.update', $tarea), 'metodo' => 'PUT'])
    </div>
@endsection
