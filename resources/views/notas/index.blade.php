@extends('layouts.app')

@section('titulo', 'Notas · FocoDiario')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="pagina-titulo">Notas</h1>
            <p class="text-secondary mb-0">
                @if ($filtro === 'bandeja')
                    Bandeja de entrada:
                @elseif ($contextoFiltro)
                    {{ $contextoFiltro->rutaCompleta() }} (incluye sus subcontextos):
                @endif
                {{ $notas->count() }} {{ $notas->count() === 1 ? 'nota' : 'notas' }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('contextos.index') }}" class="btn btn-foco-suave"><i class="bi bi-diagram-3"></i> Contextos</a>
            <a href="{{ route('notas.create', $contextoFiltro ? ['contexto' => $contextoFiltro->id] : []) }}" class="btn btn-foco"><i class="bi bi-plus-lg"></i> Nueva nota</a>
        </div>
    </div>

    <form method="GET" action="{{ route('notas.index') }}" class="tarjeta tarjeta-relleno mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-sm-8 col-lg-5">
                <label for="filtro-contexto" class="form-label">Contexto</label>
                <select id="filtro-contexto" name="contexto" class="form-select">
                    <option value="">Todas las notas</option>
                    <option value="bandeja" @selected($filtro === 'bandeja')>Bandeja de entrada ({{ $totalBandeja }})</option>
                    @foreach ($destinos as $id => $ruta)
                        <option value="{{ $id }}" @selected((string) $filtro === (string) $id)>{{ $ruta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 d-flex gap-2">
                <button type="submit" class="btn btn-foco-suave">Filtrar</button>
                @if ($filtro)
                    <a href="{{ route('notas.index') }}" class="btn btn-foco-suave">Quitar filtro</a>
                @endif
            </div>
        </div>
    </form>

    <div class="tarjeta" id="lista-notas">
        @forelse ($notas as $nota)
            @include('notas._nota')
        @empty
            <p class="estado-vacio px-4">
                @if ($filtro)
                    No hay notas en este filtro.
                @else
                    Todavía no anotaste nada. Usá la nota rápida en Hoy o el botón "Nueva nota".
                @endif
            </p>
        @endforelse
    </div>
@endsection
