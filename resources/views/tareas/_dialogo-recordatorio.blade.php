{{-- Modal de crear y editar recordatorio (mejora progresiva: sin JS, los enlaces llevan a las páginas de siempre).
     Requiere: $tareasAbiertas. Lo maneja resources/js/dialogos-tablero.js. --}}
<dialog id="dialogo-recordatorio" class="dialogo" data-modal aria-labelledby="dialogo-recordatorio-titulo"
        data-url-crear="{{ route('recordatorios.store') }}">
    <form class="dialogo-cuerpo dialogo-form" method="POST" action="{{ route('recordatorios.store') }}" novalidate data-form-modal>
        <div class="dialogo-cab">
            <h2 class="dialogo-titulo" id="dialogo-recordatorio-titulo" data-titulo-dialogo>Nuevo recordatorio</h2>
            <button type="button" class="dialogo-cerrar" data-cerrar-modal aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>

        <div class="dialogo-avisos" data-avisos role="alert"></div>

        <div>
            <label for="rec-mensaje" class="dialogo-etiqueta">Mensaje</label>
            <input type="text" id="rec-mensaje" name="mensaje" class="form-control" maxlength="255" required autocomplete="off" aria-describedby="rec-error-mensaje">
            <div class="dialogo-error" id="rec-error-mensaje" data-error="mensaje"></div>
        </div>

        <div>
            <label for="rec-descripcion" class="dialogo-etiqueta">Comentario <span class="dialogo-opcional">(opcional)</span></label>
            <textarea id="rec-descripcion" name="descripcion" rows="3" maxlength="5000" class="form-control" aria-describedby="rec-error-descripcion"></textarea>
            <div class="dialogo-error" id="rec-error-descripcion" data-error="descripcion"></div>
        </div>

        <div class="dialogo-fila">
            <div class="dialogo-campo">
                <label for="rec-recordar_en" class="dialogo-etiqueta">Recordar en <span class="dialogo-opcional">(opcional)</span></label>
                <input type="datetime-local" id="rec-recordar_en" name="recordar_en" class="form-control" aria-describedby="rec-error-recordar_en">
                <div class="dialogo-error" id="rec-error-recordar_en" data-error="recordar_en"></div>
            </div>
            <div class="dialogo-campo">
                <label for="rec-tarea_id" class="dialogo-etiqueta">Tarea <span class="dialogo-opcional">(opcional)</span></label>
                <select id="rec-tarea_id" name="tarea_id" class="form-select" aria-describedby="rec-error-tarea_id">
                    <option value="">Sin tarea</option>
                    @foreach ($tareasAbiertas as $tareaOpcion)
                        <option value="{{ $tareaOpcion->id }}">{{ $tareaOpcion->titulo }}</option>
                    @endforeach
                </select>
                <div class="dialogo-error" id="rec-error-tarea_id" data-error="tarea_id"></div>
            </div>
        </div>
        <p class="dialogo-ayuda">Sin fecha, el recordatorio queda "por ubicar" en el calendario.</p>

        <div class="dialogo-pie-acciones">
            <button type="button" class="btn btn-foco-suave" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="btn btn-foco" data-enviar data-texto-crear="Crear recordatorio" data-texto-editar="Guardar cambios">Crear recordatorio</button>
        </div>
    </form>
</dialog>
