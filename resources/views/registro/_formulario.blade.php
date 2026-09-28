{{-- Formulario de bloque de tiempo. Requiere: $bloque (nuevo o existente), $fecha (Y-m-d), $categorias, $tareas, $accion, $metodo, $cancelar (opcional) --}}
<form method="POST" action="{{ $accion }}" novalidate>
    @csrf
    @if ($metodo !== 'POST')
        @method($metodo)
    @endif
    <input type="hidden" name="fecha" value="{{ $fecha }}">

    <div class="row g-3 align-items-start">
        <div class="col-6 col-md-3">
            <label for="categoria_id" class="form-label">Categoría</label>
            <select id="categoria_id" name="categoria_id" class="form-select @error('categoria_id') is-invalid @enderror" required>
                <option value="">Elegí una</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected((string) old('categoria_id', $bloque->categoria_id) === (string) $categoria->id)>{{ $categoria->nombre }}</option>
                @endforeach
            </select>
            @error('categoria_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-3 col-md-2">
            <label for="inicio" class="form-label">Inicio</label>
            <input type="time" id="inicio" name="inicio" value="{{ old('inicio', $bloque->inicio?->format('H:i')) }}"
                   class="form-control @error('inicio') is-invalid @enderror" required>
            @error('inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-3 col-md-2">
            <label for="fin" class="form-label">Fin</label>
            <input type="time" id="fin" name="fin" value="{{ old('fin', $bloque->fin?->format('H:i')) }}"
                   class="form-control @error('fin') is-invalid @enderror" required>
            @error('fin') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-8 col-md-3">
            <label for="tarea_id" class="form-label">Tarea <span class="text-secondary fw-normal">(opcional)</span></label>
            <select id="tarea_id" name="tarea_id" class="form-select @error('tarea_id') is-invalid @enderror">
                <option value="">Sin tarea</option>
                @foreach ($tareas as $tarea)
                    <option value="{{ $tarea->id }}" @selected((string) old('tarea_id', $bloque->tarea_id) === (string) $tarea->id)>{{ $tarea->titulo }}</option>
                @endforeach
            </select>
            @error('tarea_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-4 col-md-2">
            <label for="concentracion" class="form-label">Foco <span class="text-secondary fw-normal">(1-5)</span></label>
            <select id="concentracion" name="concentracion" class="form-select @error('concentracion') is-invalid @enderror">
                <option value="">-</option>
                @foreach (range(1, 5) as $nivel)
                    <option value="{{ $nivel }}" @selected((string) old('concentracion', $bloque->concentracion) === (string) $nivel)>{{ $nivel }}</option>
                @endforeach
            </select>
            @error('concentracion') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-foco">{{ $textoBoton ?? 'Guardar' }}</button>
        @isset($cancelar)
            <a href="{{ $cancelar }}" class="btn btn-foco-suave">Cancelar</a>
        @endisset
    </div>
</form>
