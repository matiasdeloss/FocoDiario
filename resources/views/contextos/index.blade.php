@extends('layouts.app')

@section('titulo', 'Contextos · FocoDiario')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="pagina-titulo">Contextos</h1>
            <p class="text-secondary mb-0">Entornos, materias, temas y proyectos para ordenar lo que anotás y hacés.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('notas.index') }}" class="btn btn-foco-suave"><i class="bi bi-journal-text"></i> Notas</a>
            <a href="{{ route('contextos.create') }}" class="btn btn-foco"><i class="bi bi-plus-lg"></i> Nuevo contexto</a>
        </div>
    </div>

    {{-- Abierta si todavía no hay contextos (ahí explica cómo empezar); cerrada si ya hay, para no ocupar lugar. --}}
    <details class="tarjeta contextos-ayuda" @if ($arbol->isEmpty()) open @endif>
        <summary>¿Para qué sirven los contextos?</summary>
        <p>Un contexto agrupa tus notas, tareas, sesiones de estudio y actividades de la agenda bajo una misma cosa. Así ves todo lo de una materia o un proyecto junto, en lugar de buscarlo por separado. Podés anidarlos: un tema dentro de una materia, una materia dentro de un entorno.</p>
        <dl class="contextos-tipos">
            <div><dt><span class="badge-foco">Entorno</span></dt><dd>Un área grande de tu vida, como "Carrera" o "Vida cotidiana".</dd></div>
            <div><dt><span class="badge-foco">Materia</span></dt><dd>Una materia o curso que estás cursando.</dd></div>
            <div><dt><span class="badge-foco">Tema</span></dt><dd>Una unidad o tema dentro de una materia.</dd></div>
            <div><dt><span class="badge-foco">Proyecto</span></dt><dd>Algo con un objetivo y un final: un TP integrador, un portfolio, un proyecto personal.</dd></div>
        </dl>
    </details>

    <div class="tarjeta">
        @forelse ($arbol as $fila)
            @php($contexto = $fila['contexto'])
            <div class="arbol-fila" style="--nivel: {{ $fila['nivel'] }}">
                <div class="arbol-nombre">
                    @php($color = $colores->visible($contexto->id))
                    @if ($color)
                        <span class="arbol-color @unless ($color->propio) es-heredado @endunless" style="background: {{ $color->marca() }}"
                              @unless ($color->propio) title="Usa el color de {{ $color->origen }}" @endunless aria-hidden="true"></span>
                    @endif
                    <a href="{{ route('notas.index', ['contexto' => $contexto->id]) }}" class="fw-medium text-decoration-none">{{ $contexto->nombre }}</a>
                    <span class="badge-foco">{{ $contexto->tipo->etiqueta() }}</span>
                    <span class="text-secondary small">{{ $contexto->notas_count }} {{ $contexto->notas_count === 1 ? 'nota' : 'notas' }}</span>
                </div>
                <div class="text-nowrap">
                    <a href="{{ route('contextos.create', ['padre' => $contexto->id]) }}" class="btn-icono" title="Agregar subcontexto" aria-label="Agregar subcontexto a {{ $contexto->nombre }}"><i class="bi bi-node-plus"></i></a>
                    <a href="{{ route('contextos.edit', $contexto) }}" class="btn-icono" title="Editar" aria-label="Editar {{ $contexto->nombre }}"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('contextos.destroy', $contexto) }}" class="d-inline"
                          data-confirmar="¿Eliminar &quot;{{ $contexto->nombre }}&quot;? Sus notas pasan a la bandeja de entrada y sus subcontextos quedan sin padre.@if ($contexto->color) También es una actividad de la Agenda: sus cajas quedan sin actividad.@endif">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-icono" title="Eliminar" aria-label="Eliminar {{ $contexto->nombre }}"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        @empty
            <p class="estado-vacio px-4">Todavía no hay contextos. Creá el primero con "Nuevo contexto": por ejemplo un entorno como "Carrera" y, adentro, tus materias. Después podés asignarlo a notas y tareas.</p>
        @endforelse
    </div>
@endsection
