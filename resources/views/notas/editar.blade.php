@extends('layouts.app')

@section('titulo', 'Editar nota · FocoDiario')

@push('head')
    @vite(['resources/css/notas.css'])
@endpush

@section('contenido')
    <header class="notas-cab">
        <div class="notas-titulos">
            <h1 class="notas-titulo">Editar nota</h1>
        </div>
    </header>

    <div class="notas-pagina-form">
        @include('notas._formulario', ['accion' => route('notas.update', $nota), 'metodo' => 'PUT'])
    </div>
@endsection
