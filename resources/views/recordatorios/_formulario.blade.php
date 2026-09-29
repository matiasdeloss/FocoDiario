{{-- Formulario compartido de crear y editar recordatorio. Requiere: $recordatorio, $tareas, $accion, $metodo --}}
<form method="POST" action="{{ $accion }}" novalidate>
    @csrf
    @if ($metodo !== 'POST')
        @method($metodo)
    @endif

    <div class="mb-3">
        <label for="mensaje" class="form-label">Mensaje</label>
        <input type="text" id="mensaje" name="mensaje" value="{{ old('mensaje', $recordatorio->mensaje) }}"
               class="form-control @error('mensaje') is-invalid @enderror" maxlength="255" required autofocus>
        @error('mensaje') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="descripcion" class="form-label">Comentario <span class="text-secondary fw-normal">(opcional)</span></label>
        <textarea id="descripcion" name="descripcion" rows="3" maxlength="5000"
                  class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $recordatorio->descripcion) }}</textarea>
        @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label for="recordar_en" class="form-label">Recordar en <span class="text-secondary fw-normal">(opcional: sin fecha queda "por ubicar" en el calendario)</span></label>
            <input type="datetime-local" id="recordar_en" name="recordar_en"
                   value="{{ old('recordar_en', $recordatorio->recordar_en?->format('Y-m-d\TH:i')) }}"
                   class="form-control @error('recordar_en') is-invalid @enderror">
            @error('recordar_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="tarea_id" class="form-label">Tarea <span class="text-secondary fw-normal">(opcional)</span></label>
            <select id="tarea_id" name="tarea_id" class="form-select @error('tarea_id') is-invalid @enderror">
                <option value="">Sin tarea</option>
                @foreach ($tareas as $tarea)
                    <option value="{{ $tarea->id }}" @selected((string) old('tarea_id', $recordatorio->tarea_id) === (string) $tarea->id)>{{ $tarea->titulo }}</option>
                @endforeach
            </select>
            @error('tarea_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-foco">Guardar</button>
        <a href="{{ route('recordatorios.index') }}" class="btn btn-foco-suave">Cancelar</a>
    </div>
</form>
