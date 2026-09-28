@extends('layouts.app')

@section('titulo', 'Contextos · FocoDiario')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="pagina-titulo">Contextos</h1>
            <p class="text-secondary mb-0">Entornos, materias y temas donde guardar tus notas.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('notas.index') }}" class="btn btn-foco-suave"><i class="bi bi-journal-text"></i> Notas</a>
            <a href="{{ route('contextos.create') }}" class="btn btn-foco"><i class="bi bi-plus-lg"></i> Nuevo contexto</a>
        </div>
    </div>

    <div class="tarjeta">
        @forelse ($arbol as $fila)
            @php($contexto = $fila['contexto'])
            <div class="arbol-fila" style="--nivel: {{ $fila['nivel'] }}">
                <div class="arbol-nombre">
                    @if ($contexto->color)
                        <span class="arbol-color" style="background: {{ $contexto->color }}" aria-hidden="true"></span>
                    @endif
                    <a href="{{ route('notas.index', ['contexto' => $contexto->id]) }}" class="fw-medium text-decoration-none">{{ $contexto->nombre }}</a>
                    <span class="badge-foco">{{ $contexto->tipo->etiqueta() }}</span>
                    <span class="text-secondary small">{{ $contexto->notas_count }} {{ $contexto->notas_count === 1 ? 'nota' : 'notas' }}</span>
                </div>
                <div class="text-nowrap">
                    <a href="{{ route('contextos.create', ['padre' => $contexto->id]) }}" class="btn-icono" title="Agregar subcontexto" aria-label="Agregar subcontexto a {{ $contexto->nombre }}"><i class="bi bi-node-plus"></i></a>
                    <a href="{{ route('contextos.edit', $contexto) }}" class="btn-icono" title="Editar" aria-label="Editar {{ $contexto->nombre }}"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('contextos.destroy', $contexto) }}" class="d-inline"
                          onsubmit="return confirm(@js('¿Eliminar "' . $contexto->nombre . '"? Sus notas pasan a la bandeja de entrada y sus subcontextos quedan sin padre.'))">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar {{ $contexto->nombre }}"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        @empty
            <p class="estado-vacio px-4">Todavía no hay contextos. Empezá con el botón "Nuevo contexto".</p>
        @endforelse
    </div>
@endsection
