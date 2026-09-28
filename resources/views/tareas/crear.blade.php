@extends('layouts.app')

@section('titulo', 'Nueva tarea · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">Nueva tarea</h1>
    </div>

    <div class="tarjeta p-4 formulario-angosto">
        @include('tareas._formulario', ['accion' => route('tareas.store'), 'metodo' => 'POST'])
    </div>
@endsection
