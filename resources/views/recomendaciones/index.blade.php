@extends('layouts.app')

@section('titulo', 'Recomendaciones · FocoDiario')

@section('contenido')
    <div class="contenido-lectura">
        <div class="mb-4">
            <h1 class="pagina-titulo">Recomendaciones</h1>
            <p class="text-secondary mb-0">Se calculan con tu registro de hoy, tus tareas y recordatorios. Tu horario de sueño no se registra: lo sugiere el sistema.</p>
        </div>

        @forelse ($grupos as $grupo)
            <section class="tarjeta tarjeta-relleno mb-3" aria-labelledby="grupo-{{ $grupo['tipo']->value }}">
                <h2 class="tarjeta-titulo" id="grupo-{{ $grupo['tipo']->value }}">{{ $grupo['tipo']->etiquetaPlural() }}</h2>
                @foreach ($grupo['items'] as $recomendacion)
                    @include('recomendaciones._item', ['recomendacion' => $recomendacion, 'mostrarFuente' => true])
                @endforeach
            </section>
        @empty
            <p class="estado-vacio">Por ahora no hay recomendaciones.</p>
        @endforelse
    </div>
@endsection
