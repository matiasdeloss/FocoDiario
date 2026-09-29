{{-- Formulario compartido de crear y editar nota. Requiere: $nota, $destinos, $accion, $metodo --}}
<form method="POST" action="{{ $accion }}" novalidate>
    @csrf
    @if ($metodo !== 'POST')
        @method($metodo)
    @endif

    <div class="mb-3">
        <label for="titulo" class="form-label">Título <span class="text-secondary fw-normal">(opcional)</span></label>
        <input type="text" id="titulo" name="titulo" value="{{ old('titulo', $nota->titulo) }}"
               class="form-control @error('titulo') is-invalid @enderror" maxlength="255" autofocus>
        @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="contenido" class="form-label">Nota</label>
        <textarea id="contenido" name="contenido" rows="5" maxlength="5000"
                  class="form-control @error('contenido') is-invalid @enderror">{{ old('contenido', $nota->contenido) }}</textarea>
        @error('contenido') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label for="contexto_id" class="form-label">Destino</label>
            <select id="contexto_id" name="contexto_id" class="form-select @error('contexto_id') is-invalid @enderror">
                <option value="">Bandeja de entrada</option>
                @foreach ($destinos as $id => $ruta)
                    <option value="{{ $id }}" @selected((string) old('contexto_id', $nota->contexto_id) === (string) $id)>{{ $ruta }}</option>
                @endforeach
            </select>
            @error('contexto_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="fecha" class="form-label">Fecha <span class="text-secondary fw-normal">(opcional)</span></label>
            <input type="date" id="fecha" name="fecha" value="{{ old('fecha', $nota->fecha?->format('Y-m-d')) }}"
                   class="form-control @error('fecha') is-invalid @enderror">
            @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="form-check mb-4">
        <input type="hidden" name="fijada" value="0">
        <input type="checkbox" class="form-check-input" id="fijada" name="fijada" value="1" @checked(old('fijada', $nota->fijada))>
        <label class="form-check-label" for="fijada">Fijar arriba en el listado</label>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-foco">Guardar</button>
        <a href="{{ route('notas.index') }}" class="btn btn-foco-suave">Cancelar</a>
    </div>
</form>
