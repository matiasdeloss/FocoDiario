@extends('layouts.app')

@section('titulo', 'Nueva nota · FocoDiario')

@push('head')
    @vite(['resources/css/notas.css'])
@endpush

@section('contenido')
    <header class="notas-cab">
        <div class="notas-titulos">
            <h1 class="notas-titulo">Nueva nota</h1>
        </div>
    </header>

    <div class="notas-pagina-form">
        @include('notas._formulario', ['accion' => route('notas.store'), 'metodo' => 'POST'])
    </div>
@endsection
