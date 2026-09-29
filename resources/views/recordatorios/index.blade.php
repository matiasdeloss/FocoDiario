@extends('layouts.app')

@section('titulo', 'Recordatorios · FocoDiario')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="pagina-titulo">Recordatorios</h1>
            <p class="text-secondary mb-0">Los pendientes aparecen primero, por fecha. Los que no tienen fecha van aparte.</p>
        </div>
        <a href="{{ route('recordatorios.create') }}" class="btn btn-foco"><i class="bi bi-plus-lg"></i> Nuevo recordatorio</a>
    </div>

    <div class="tarjeta">
        @if ($recordatorios->isEmpty() && $sinFecha->isEmpty())
            <p class="estado-vacio px-4">No hay recordatorios. Creá el primero con el botón "Nuevo recordatorio".</p>
        @else
            <div class="tabla-foco-contenedor">
                <table class="table tabla-foco">
                    <thead>
                        <tr>
                            <th scope="col">Mensaje</th>
                            <th scope="col">Recordar en</th>
                            <th scope="col">Estado</th>
                            <th scope="col"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recordatorios as $recordatorio)
                            @include('recordatorios._fila')
                        @endforeach
                    </tbody>
                    @if ($sinFecha->isNotEmpty())
                        <tbody>
                            <tr class="fila-grupo">
                                <th scope="rowgroup" colspan="4">Sin fecha <span class="fw-normal text-secondary">(ubicalos arrastrándolos en el calendario)</span></th>
                            </tr>
                            @foreach ($sinFecha as $recordatorio)
                                @include('recordatorios._fila')
                            @endforeach
                        </tbody>
                    @endif
                </table>
            </div>
        @endif
    </div>
@endsection
