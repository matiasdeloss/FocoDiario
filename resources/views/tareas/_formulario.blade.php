{{-- Formulario compartido de crear y editar tarea. Requiere: $tarea, $prioridades, $estados, $contextos, $accion, $metodo --}}
<form method="POST" action="{{ $accion }}" novalidate>
    @csrf
    @if ($metodo !== 'POST')
        @method($metodo)
    @endif

    <div class="mb-3">
        <label for="titulo" class="form-label">Título</label>
        <input type="text" id="titulo" name="titulo" value="{{ old('titulo', $tarea->titulo) }}"
               class="form-control @error('titulo') is-invalid @enderror" maxlength="255" required autofocus>
        @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="descripcion" class="form-label">Comentario <span class="text-secondary fw-normal">(opcional)</span></label>
        <textarea id="descripcion" name="descripcion" rows="3" maxlength="5000"
                  class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $tarea->descripcion) }}</textarea>
        @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label for="contexto_id" class="form-label">Contexto <span class="text-secondary fw-normal">(opcional)</span></label>
            <select id="contexto_id" name="contexto_id" class="form-select @error('contexto_id') is-invalid @enderror">
                <option value="">Sin contexto</option>
                @include('tareas._opciones-contexto', ['grupos' => $contextos, 'elegido' => old('contexto_id', $tarea->contexto_id)])
            </select>
            @error('contexto_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="fecha_limite" class="form-label">Fecha límite <span class="text-secondary fw-normal">(opcional)</span></label>
            <input type="date" id="fecha_limite" name="fecha_limite"
                   value="{{ old('fecha_limite', $tarea->fecha_limite?->format('Y-m-d')) }}"
                   class="form-control @error('fecha_limite') is-invalid @enderror">
            @error('fecha_limite') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label for="prioridad" class="form-label">Prioridad</label>
            <select id="prioridad" name="prioridad" class="form-select @error('prioridad') is-invalid @enderror" required>
                @foreach ($prioridades as $prioridad)
                    <option value="{{ $prioridad->value }}" @selected(old('prioridad', $tarea->prioridad?->value) === $prioridad->value)>{{ $prioridad->etiqueta() }}</option>
                @endforeach
            </select>
            @error('prioridad') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="estado" class="form-label">Estado</label>
            <select id="estado" name="estado" class="form-select @error('estado') is-invalid @enderror" required>
                @foreach ($estados as $estado)
                    <option value="{{ $estado->value }}" @selected(old('estado', $tarea->estado?->value) === $estado->value)>{{ $estado->etiqueta() }}</option>
                @endforeach
            </select>
            @error('estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-foco">Guardar</button>
        <a href="{{ route('tareas.index') }}" class="btn btn-foco-suave">Cancelar</a>
    </div>
</form>
