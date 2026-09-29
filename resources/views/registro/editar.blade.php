@extends('layouts.app')

@section('titulo', 'Editar bloque · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">Editar bloque</h1>
        <p class="text-secondary mb-0">{{ $bloque->inicio->translatedFormat('l j \d\e F \d\e Y') }}</p>
    </div>

    <div class="tarjeta tarjeta-relleno">
        @include('registro._formulario', [
            'accion' => route('registro.update', $bloque),
            'metodo' => 'PUT',
            'fecha' => $bloque->inicio->format('Y-m-d'),
            'cancelar' => route('registro.index', ['fecha' => $bloque->inicio->format('Y-m-d')]),
        ])
    </div>
@endsection
