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
    </fieldset>

    <div class="row g-3" data-calendario
         data-fecha="{{ $fechaInicial }}"
         data-url-eventos="{{ route('calendario.eventos') }}"
         data-url-tarea="{{ route('calendario.tareas.fecha', ['tarea' => '__ID__']) }}"
         data-url-recordatorio="{{ route('calendario.recordatorios.fecha', ['recordatorio' => '__ID__']) }}"
         data-url-nota="{{ route('calendario.notas.fecha', ['nota' => '__ID__']) }}"
         data-url-tarjetas="{{ route('calendario.tarjetas.store') }}"
         data-url-tarjeta="{{ route('calendario.tarjetas.update', ['tipo' => '__TIPO__', 'id' => '__ID__']) }}"
         data-url-detalle="{{ route('calendario.detalle', ['tipo' => '__TIPO__', 'id' => '__ID__']) }}"
         data-url-pagina="{{ route('calendario.tarjetas.index', ['tipo' => '__TIPO__']) }}">
        <div class="col-lg-8">
            <div class="tarjeta tarjeta-relleno">
                <div id="calendario" aria-label="Calendario"></div>
            </div>
        </div>

        <div class="col-lg-4">
            <aside class="tarjeta tarjeta-relleno panel-sin-fecha" id="panel-sin-fecha" aria-labelledby="panel-sin-fecha-titulo">
                <header class="panel-cab">
                    <h2 class="tarjeta-titulo" id="panel-sin-fecha-titulo">Por ubicar</h2>
                    <p class="panel-ayuda">Arrastrá una tarjeta a un día del calendario o elegí su fecha. Para quitársela, arrastrá el evento de vuelta acá.</p>
                </header>

                <div class="panel-crear" role="group" aria-label="Crear una tarjeta nueva">
                    <button type="button" class="crear-boton tipo-tarea" data-crear="tarea" aria-label="Nueva tarea"><i class="bi bi-plus-lg" aria-hidden="true"></i> Tarea</button>
                    <button type="button" class="crear-boton tipo-recordatorio" data-crear="recordatorio" aria-label="Nuevo recordatorio"><i class="bi bi-plus-lg" aria-hidden="true"></i> Recordatorio</button>
                    <button type="button" class="crear-boton tipo-nota" data-crear="nota" aria-label="Nueva nota"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nota</button>
                </div>

                <div class="panel-filtros" role="group" aria-label="Filtrar el panel por tipo">
                    <button type="button" class="panel-filtro" data-panel-filtro="" aria-pressed="true">Todo</button>
                    <button type="button" class="panel-filtro tipo-tarea" data-panel-filtro="tarea" aria-pressed="false"><span class="filtro-punto" aria-hidden="true"></span>Tareas</button>
                    <button type="button" class="panel-filtro tipo-recordatorio" data-panel-filtro="recordatorio" aria-pressed="false"><span class="filtro-punto" aria-hidden="true"></span>Recordatorios</button>
                    <button type="button" class="panel-filtro tipo-nota" data-panel-filtro="nota" aria-pressed="false"><span class="filtro-punto" aria-hidden="true"></span>Notas</button>
                </div>

                <ul class="panel-sin-fecha-lista list-unstyled mb-0" id="lista-sin-fecha">
                    @foreach ($tarjetas as $t)
                        @include('calendario._tarjeta', ['t' => $t])
                    @endforeach
                </ul>
                <p class="estado-vacio small" id="sin-fecha-vacio" @if ($tarjetas->isNotEmpty()) hidden @endif>No hay nada por ubicar. Creá una tarjeta con los botones de arriba.</p>

                <div class="panel-mas" id="panel-mas">
                    @foreach (['tarea' => 'tareas', 'recordatorio' => 'recordatorios', 'nota' => 'notas'] as $tipo => $plural)
                        <button type="button" class="btn btn-foco-suave btn-sm" data-ver-mas="{{ $tipo }}" @if (! $hayMas[$tipo]) hidden @endif>Ver más {{ $plural }}</button>
                    @endforeach
                </div>
            </aside>
        </div>
    </div>

    {{-- Menú para elegir el tipo al tocar un día vacío --}}
    <div class="popover-foco popover-tipos" id="popover-tipos" role="dialog" aria-label="Crear en este día" hidden>
        <p class="popover-foco-titulo" id="popover-tipos-fecha"></p>
        <div class="popover-tipos-botones">
            <button type="button" class="crear-boton tipo-tarea" data-crear-en-dia="tarea"><i class="bi bi-check2-square" aria-hidden="true"></i> Tarea</button>
            <button type="button" class="crear-boton tipo-recordatorio" data-crear-en-dia="recordatorio"><i class="bi bi-bell" aria-hidden="true"></i> Recordatorio</button>
            <button type="button" class="crear-boton tipo-nota" data-crear-en-dia="nota"><i class="bi bi-journal-text" aria-hidden="true"></i> Nota</button>
        </div>
    </div>

    {{-- Panel lateral con el detalle de la card tocada (hoja desde abajo en pantallas angostas) --}}
    <aside class="detalle" id="detalle" role="dialog" aria-modal="false" aria-labelledby="detalle-tipo" tabindex="-1" hidden>
        <header class="detalle-cab">
            <span class="detalle-pill" id="detalle-tipo"></span>
            <button type="button" class="btn-icono" data-detalle-cerrar aria-label="Cerrar el detalle"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </header>

        <div class="detalle-cuerpo" id="detalle-cuerpo" aria-busy="false">
            <label class="visually-hidden" for="detalle-titulo">Título</label>
            <textarea id="detalle-titulo" class="tarj-campo detalle-titulo" data-campo="titulo" rows="1" maxlength="255" placeholder="Título" autocomplete="off" data-tipos="tarea recordatorio nota"></textarea>
            <h2 class="detalle-titulo-fijo" id="detalle-titulo-fijo" data-tipos="sesion"></h2>

            <p class="detalle-aviso" id="detalle-estado" hidden></p>

            <div class="detalle-campo" data-tipos="tarea recordatorio nota">
                <label class="detalle-etiqueta" for="detalle-comentario" id="detalle-comentario-etiqueta">Descripción</label>
                <textarea id="detalle-comentario" class="tarj-campo tarj-comentario detalle-texto" data-campo="comentario" rows="3" maxlength="5000" placeholder="Agregar texto"></textarea>
            </div>

            <div class="detalle-campo" data-tipos="tarea">
                <span class="detalle-etiqueta" id="detalle-prioridad-etiqueta">Prioridad</span>
                <div class="detalle-opciones" id="detalle-prioridad" role="radiogroup" aria-labelledby="detalle-prioridad-etiqueta"></div>
            </div>

            <div class="detalle-campo" data-tipos="nota">
                <span class="detalle-etiqueta" id="detalle-color-etiqueta">Color</span>
                <div class="detalle-opciones" id="detalle-color" role="radiogroup" aria-labelledby="detalle-color-etiqueta"></div>
            </div>

            <div class="detalle-campo" data-tipos="tarea recordatorio nota">
                <label class="detalle-etiqueta" for="detalle-fecha" id="detalle-fecha-etiqueta">Fecha</label>
                <div class="detalle-fecha-fila">
                    <input type="date" id="detalle-fecha" class="form-control form-control-sm">
                    <button type="button" class="detalle-enlace" id="detalle-quitar-fecha">Quitar fecha</button>
                </div>
                <p class="detalle-ayuda" id="detalle-fecha-ayuda" hidden></p>
            </div>

            {{-- El resto de los campos (proyecto, estado, materia, tarea vinculada…) se editan acá mismo (calendario-campos.js). --}}
            <div class="detalle-mas" id="detalle-mas"></div>

            <dl class="detalle-filas" id="detalle-filas"></dl>

            <div class="detalle-acciones">
                <button type="button" class="btn btn-sm btn-foco-suave detalle-boton" id="detalle-completar" data-tipos="tarea" aria-pressed="false"></button>
                <a href="#" class="detalle-boton detalle-enlace-boton" id="detalle-historial" data-tipos="sesion"><i class="bi bi-clock-history" aria-hidden="true"></i> Ver el historial de Estudio</a>
            </div>
        </div>

        <footer class="detalle-pie" id="detalle-pie">
            <span class="tarj-guardado" id="detalle-guardado" role="status" aria-live="polite"></span>
            <button type="button" class="btn-icono tarj-borrar" id="detalle-borrar" data-tipos="tarea recordatorio nota" aria-label="Eliminar" title="Eliminar"><i class="bi bi-trash" aria-hidden="true"></i></button>
        </footer>
        <div class="detalle-confirmar" id="detalle-confirmar" role="alertdialog" aria-labelledby="detalle-confirmar-texto" hidden>
            <p id="detalle-confirmar-texto"></p>
            <div>
                <button type="button" class="btn btn-sm btn-foco-suave detalle-boton" id="detalle-confirmar-no">Cancelar</button>
                <button type="button" class="btn btn-sm btn-foco-peligro detalle-boton" id="detalle-confirmar-si">Eliminar</button>
            </div>
        </div>
    </aside>
@endsection
