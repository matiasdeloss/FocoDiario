@extends('layouts.app')

@section('titulo', 'Calendario · FocoDiario')

@section('contenido')
    <div class="mb-4">
        <h1 class="pagina-titulo">Calendario</h1>
        <p class="text-secondary mb-0">Tareas, recordatorios, notas y sesiones de estudio en un solo lugar.</p>
    </div>

    <div id="calendario-aviso" class="aviso-foco aviso-error" role="alert" hidden></div>

    <fieldset class="calendario-filtros mb-3">
        <legend class="visually-hidden">Qué mostrar en el calendario</legend>
        @foreach ([
            'tarea' => 'Tareas',
            'recordatorio' => 'Recordatorios',
            'nota' => 'Notas',
            'sesion' => 'Estudio',
        ] as $tipo => $etiqueta)
            <label class="filtro-chip tipo-{{ $tipo }}">
                <input type="checkbox" value="{{ $tipo }}" data-filtro-tipo checked>
                <span class="filtro-punto" aria-hidden="true"></span>
                {{ $etiqueta }}
            </label>
        @endforeach
        <button type="button" class="btn btn-foco ms-auto" data-abrir-modal><i class="bi bi-plus-lg"></i> Agregar</button>
    </fieldset>

    <div class="row g-3" data-calendario
         data-fecha="{{ $fechaInicial }}"
         data-url-eventos="{{ route('calendario.eventos') }}"
         data-url-tarea="{{ route('calendario.tareas.fecha', ['tarea' => '__ID__']) }}"
         data-url-recordatorio="{{ route('calendario.recordatorios.fecha', ['recordatorio' => '__ID__']) }}"
         data-url-tarea-nueva="{{ route('tareas.create') }}"
         data-url-recordatorio-nuevo="{{ route('recordatorios.create') }}"
         data-url-nota-nueva="{{ route('notas.create') }}">
        <div class="col-lg-9">
            <div class="tarjeta p-2 p-md-3">
                <div id="calendario" aria-label="Calendario"></div>
            </div>
        </div>

        <div class="col-lg-3">
            <aside class="tarjeta p-3 panel-sin-fecha" id="panel-sin-fecha" aria-labelledby="panel-sin-fecha-titulo">
                <h2 class="tarjeta-titulo" id="panel-sin-fecha-titulo">Tareas sin fecha</h2>
                <p class="small text-secondary">Arrastrá una tarea a un día para darle fecha límite, o elegí la fecha en su campo. Para quitarla, arrastrala de vuelta acá.</p>
                <ul class="panel-sin-fecha-lista list-unstyled mb-0" id="lista-sin-fecha">
                    @foreach ($sinFecha as $tarea)
                        @include('calendario._tarea-panel', ['tarea' => $tarea])
                    @endforeach
                </ul>
                <p class="estado-vacio small" id="sin-fecha-vacio" @if ($sinFecha->isNotEmpty()) hidden @endif>Todas tus tareas abiertas tienen fecha.</p>
            </aside>
        </div>
    </div>

    <div class="modal fade" id="modal-dia" tabindex="-1" aria-labelledby="modal-dia-titulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content tarjeta">
                <div class="modal-header border-0 pb-0">
                    <h2 class="modal-title fs-5" id="modal-dia-titulo">Agregar al día</h2>
                    <button type="button" class="btn-icono" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="modal-body">
                    <label for="modal-dia-fecha" class="form-label">Día</label>
                    <input type="date" id="modal-dia-fecha" class="form-control mb-3">
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-foco-suave" data-nuevo="tarea"><i class="bi bi-check2-square"></i> Nueva tarea</a>
                        <a href="#" class="btn btn-foco-suave" data-nuevo="recordatorio"><i class="bi bi-bell"></i> Nuevo recordatorio</a>
                        <a href="#" class="btn btn-foco-suave" data-nuevo="nota"><i class="bi bi-journal-text"></i> Nueva nota</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
