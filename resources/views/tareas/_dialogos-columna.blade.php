{{-- Modales de columna: crear/editar y eliminar (con columna destino). Requiere: $columnasOrden.
     Sin JS, el menú de cada columna trae los formularios de siempre dentro de <noscript>. --}}
<dialog id="dialogo-columna" class="dialogo dialogo-chico" data-modal aria-labelledby="dialogo-columna-titulo"
        data-url-crear="{{ route('tablero.columnas.store') }}">
    <form class="dialogo-cuerpo dialogo-form" method="POST" action="{{ route('tablero.columnas.store') }}" novalidate data-form-modal>
        <div class="dialogo-cab">
            <h2 class="dialogo-titulo" id="dialogo-columna-titulo" data-titulo-dialogo>Nueva columna</h2>
            <button type="button" class="dialogo-cerrar" data-cerrar-modal aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>

        <div class="dialogo-avisos" data-avisos role="alert"></div>

        <div>
            <label for="columna-nombre" class="dialogo-etiqueta">Nombre</label>
            <input type="text" id="columna-nombre" name="nombre" class="form-control" maxlength="60" required autocomplete="off" aria-describedby="columna-error-nombre">
            <div class="dialogo-error" id="columna-error-nombre" data-error="nombre"></div>
        </div>

        <div>
            <label for="columna-categoria" class="dialogo-etiqueta">Cuenta como</label>
            <select id="columna-categoria" name="categoria" class="form-select" aria-describedby="columna-error-categoria columna-ayuda-categoria">
                @foreach (\App\Enums\EstadoTarea::cases() as $opcion)
                    <option value="{{ $opcion->value }}" @selected($opcion === \App\Enums\EstadoTarea::EnProgreso)>{{ $opcion->etiqueta() }}</option>
                @endforeach
            </select>
            <div class="dialogo-error" id="columna-error-categoria" data-error="categoria"></div>
            <p class="dialogo-ayuda" id="columna-ayuda-categoria" data-ayuda-categoria>Las tareas que caigan en esta columna toman ese estado.</p>
        </div>

        <div class="dialogo-pie-acciones">
            <button type="button" class="btn btn-foco-suave" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="btn btn-foco" data-enviar data-texto-crear="Crear columna" data-texto-editar="Guardar cambios">Crear columna</button>
        </div>
    </form>
</dialog>

<dialog id="dialogo-columna-eliminar" class="dialogo dialogo-chico" data-modal aria-labelledby="dialogo-columna-eliminar-titulo">
    <form class="dialogo-cuerpo dialogo-form" method="POST" action="#" novalidate data-form-modal>
        <div class="dialogo-cab">
            <h2 class="dialogo-titulo" id="dialogo-columna-eliminar-titulo">Eliminar columna</h2>
            <button type="button" class="dialogo-cerrar" data-cerrar-modal aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>

        <div class="dialogo-avisos" data-avisos role="alert"></div>

        <p class="dialogo-texto" data-texto-eliminar></p>

        <div data-bloque-destino>
            <label for="columna-destino" class="dialogo-etiqueta">Pasar sus tareas a</label>
            <select id="columna-destino" name="reasignar_a" class="form-select" aria-describedby="columna-error-reasignar_a"></select>
            <div class="dialogo-error" id="columna-error-reasignar_a" data-error="reasignar_a"></div>
            <p class="dialogo-ayuda">Cada tarea toma el estado de la columna de destino.</p>
        </div>

        <div class="dialogo-pie-acciones">
            <button type="button" class="btn btn-foco-suave" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="btn btn-foco-peligro" data-enviar><i class="bi bi-trash" aria-hidden="true"></i> Eliminar columna</button>
        </div>
    </form>
</dialog>
