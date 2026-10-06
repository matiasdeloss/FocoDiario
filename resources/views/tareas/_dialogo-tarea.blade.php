{{-- Modal de crear y editar tarea (mejora progresiva: sin JS, los enlaces llevan a las páginas de siempre).
     Requiere: $columnasOrden, $contextos. Lo maneja resources/js/dialogos-tablero.js. --}}
@php
    // Una tarea nueva cae en "Sin asignar": la del tablero que se está mirando o, fuera del tablero, la del principal.
    $columnasSelector = $columnasTodas ?? $columnasOrden;
    $columnaInicial = (isset($tableroActual) ? $columnasSelector->first(fn ($c) => $c->fija && $c->tablero_id === $tableroActual->id) : null)
        ?? $columnasSelector->first(fn ($c) => $c->fija && $c->tablero?->principal) ??
        $columnasSelector->firstWhere('categoria', \App\Enums\EstadoTarea::Pendiente) ?? $columnasOrden->first();
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
            @include('tareas._campo-extra', ['campo' => 'contexto', 'prefijo' => 'tarea', 'estilo' => 'dialogo'])
            <div class="dialogo-campo">
                <label for="tarea-fecha" class="dialogo-etiqueta">Fecha límite <span class="dialogo-opcional">(opcional)</span></label>
                <input type="date" id="tarea-fecha" name="fecha_limite" class="form-control" aria-describedby="tarea-error-fecha_limite">
                <div class="dialogo-error" id="tarea-error-fecha_limite" data-error="fecha_limite"></div>
            </div>
        </div>

        <fieldset class="dialogo-grupo">
            <legend>Color de la tarjeta <span class="dialogo-opcional">(opcional)</span></legend>
            <div class="paleta-tarjeta">
                <label class="color-opcion" title="Sin color (usa el del contexto)">
                    <input type="radio" name="color" value="" checked>
                    <span class="color-opcion-punto color-opcion-ninguno" aria-hidden="true"></span>
                    <span class="visually-hidden">Sin color (usa el del contexto)</span>
                </label>
                @foreach (\App\Enums\ColorActividad::cases() as $color)
                    <label class="color-opcion" title="{{ $color->etiqueta() }}" style="--opcion-fondo: {{ $color->fondo() }}; --opcion-marca: {{ $color->marca() }}">
                        <input type="radio" name="color" value="{{ $color->value }}">
                        <span class="color-opcion-punto" aria-hidden="true"></span>
                        <span class="visually-hidden">{{ $color->etiqueta() }}</span>
                    </label>
                @endforeach
            </div>
            @include('partials.pista-color')
            <div class="dialogo-error" data-error="color"></div>
        </fieldset>

        <div class="dialogo-fila">
            @include('tareas._campo-extra', ['campo' => 'prioridad', 'prefijo' => 'tarea', 'estilo' => 'dialogo'])
            @include('tareas._campo-extra', ['campo' => 'columna', 'prefijo' => 'tarea', 'estilo' => 'dialogo'])
        </div>

        {{-- Notas vinculadas: se buscan entre las notas del usuario y se guardan junto con la tarea (dialogos-tablero.js). --}}
        <fieldset class="dialogo-grupo" data-notas-zona data-url-buscar="{{ route('notas.buscar') }}" hidden data-requiere-js>
            <legend>Notas vinculadas <span class="dialogo-opcional">(opcional)</span></legend>
            <ul class="notas-vinc-lista" data-notas-lista aria-label="Notas vinculadas"></ul>
            <p class="dialogo-ayuda" data-notas-vacia>Todavía no hay notas vinculadas.</p>
            <label for="tarea-notas-buscar" class="visually-hidden">Buscar una nota para vincular</label>
            <input type="search" id="tarea-notas-buscar" class="form-control" placeholder="Buscar una nota para vincular…" maxlength="100" autocomplete="off" data-notas-buscar>
            <ul class="notas-vinc-resultados" data-notas-resultados aria-label="Notas encontradas"></ul>
            <div class="dialogo-error" data-error="notas"></div>
        </fieldset>

        <div class="dialogo-pie-acciones">
            <button type="button" class="btn btn-foco-suave" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="btn btn-foco" data-enviar data-texto-crear="Crear tarea" data-texto-editar="Guardar cambios">Crear tarea</button>
        </div>
    </form>
</dialog>
