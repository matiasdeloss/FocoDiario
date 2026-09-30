{{-- Modal de crear y editar tarea (mejora progresiva: sin JS, los enlaces llevan a las páginas de siempre).
     Requiere: $columnasOrden, $proyectos. Lo maneja resources/js/dialogos-tablero.js. --}}
@php
    $columnaInicial = $columnasOrden->firstWhere('categoria', \App\Enums\EstadoTarea::Pendiente) ?? $columnasOrden->first();
@endphp
<dialog id="dialogo-tarea" class="dialogo" data-modal aria-labelledby="dialogo-tarea-titulo"
        data-url-crear="{{ route('tareas.store') }}" data-columna-inicial="{{ $columnaInicial?->id }}">
    <form class="dialogo-cuerpo dialogo-form" method="POST" action="{{ route('tareas.store') }}" novalidate data-form-modal>
        <div class="dialogo-cab">
            <h2 class="dialogo-titulo" id="dialogo-tarea-titulo" data-titulo-dialogo>Nueva tarea</h2>
            <button type="button" class="dialogo-cerrar" data-cerrar-modal aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>

        <div class="dialogo-avisos" data-avisos role="alert"></div>

        <div>
            <label for="tarea-titulo" class="dialogo-etiqueta">Título</label>
            <input type="text" id="tarea-titulo" name="titulo" class="form-control" maxlength="255" required autocomplete="off" aria-describedby="tarea-error-titulo">
            <div class="dialogo-error" id="tarea-error-titulo" data-error="titulo"></div>
        </div>

        <div>
            <label for="tarea-descripcion" class="dialogo-etiqueta">Comentario <span class="dialogo-opcional">(opcional)</span></label>
            <textarea id="tarea-descripcion" name="descripcion" rows="3" maxlength="5000" class="form-control" placeholder="Agregá un comentario: detalles, links, próximos pasos…" aria-describedby="tarea-error-descripcion"></textarea>
            <div class="dialogo-error" id="tarea-error-descripcion" data-error="descripcion"></div>
        </div>

        <div class="dialogo-fila">
            <div class="dialogo-campo">
                <label for="tarea-proyecto" class="dialogo-etiqueta">Proyecto <span class="dialogo-opcional">(opcional)</span></label>
                <input type="text" id="tarea-proyecto" name="proyecto" class="form-control" maxlength="255" list="tarea-lista-proyectos" autocomplete="off" aria-describedby="tarea-error-proyecto">
                <datalist id="tarea-lista-proyectos">
                    @foreach ($proyectos as $proyecto)
                        <option value="{{ $proyecto }}">
                    @endforeach
                </datalist>
                <div class="dialogo-error" id="tarea-error-proyecto" data-error="proyecto"></div>
            </div>
            <div class="dialogo-campo">
                <label for="tarea-fecha" class="dialogo-etiqueta">Fecha límite <span class="dialogo-opcional">(opcional)</span></label>
                <input type="date" id="tarea-fecha" name="fecha_limite" class="form-control" aria-describedby="tarea-error-fecha_limite">
                <div class="dialogo-error" id="tarea-error-fecha_limite" data-error="fecha_limite"></div>
            </div>
        </div>

        <fieldset class="dialogo-grupo">
            <legend>Color de la tarjeta <span class="dialogo-opcional">(opcional)</span></legend>
            <div class="paleta-tarjeta">
                <label class="color-opcion" title="Sin color">
                    <input type="radio" name="color" value="" checked>
                    <span class="color-opcion-punto color-opcion-ninguno" aria-hidden="true"></span>
                    <span class="visually-hidden">Sin color</span>
                </label>
                @foreach (\App\Enums\ColorNota::cases() as $color)
                    <label class="color-opcion" title="{{ $color->etiqueta() }}" style="--opcion-fondo: {{ $color->fondo() }}; --opcion-marca: {{ $color->marca() }}">
                        <input type="radio" name="color" value="{{ $color->value }}">
                        <span class="color-opcion-punto" aria-hidden="true"></span>
                        <span class="visually-hidden">{{ $color->etiqueta() }}</span>
                    </label>
                @endforeach
            </div>
            <div class="dialogo-error" data-error="color"></div>
        </fieldset>

        <div class="dialogo-fila">
            <div class="dialogo-campo">
                <label for="tarea-prioridad" class="dialogo-etiqueta">Prioridad</label>
                <select id="tarea-prioridad" name="prioridad" class="form-select" required aria-describedby="tarea-error-prioridad">
                    @foreach (\App\Enums\PrioridadTarea::cases() as $prioridad)
                        <option value="{{ $prioridad->value }}" @selected($prioridad === \App\Enums\PrioridadTarea::Media)>{{ $prioridad->etiqueta() }}</option>
                    @endforeach
                </select>
                <div class="dialogo-error" id="tarea-error-prioridad" data-error="prioridad"></div>
            </div>
            <div class="dialogo-campo">
                <label for="tarea-columna" class="dialogo-etiqueta">Columna</label>
                <select id="tarea-columna" name="columna_id" class="form-select" required aria-describedby="tarea-error-columna_id">
                    @foreach ($columnasOrden as $columna)
                        <option value="{{ $columna->id }}">{{ $columna->nombre }}@if (mb_strtolower($columna->nombre) !== mb_strtolower($columna->categoria->etiqueta())) ({{ $columna->categoria->etiqueta() }})@endif</option>
                    @endforeach
                </select>
                <div class="dialogo-error" id="tarea-error-columna_id" data-error="columna_id"></div>
            </div>
        </div>

        <div class="dialogo-pie-acciones">
            <button type="button" class="btn btn-foco-suave" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="btn btn-foco" data-enviar data-texto-crear="Crear tarea" data-texto-editar="Guardar cambios">Crear tarea</button>
        </div>
    </form>
</dialog>
