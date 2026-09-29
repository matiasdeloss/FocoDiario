{{-- Formulario compartido de crear y editar contexto. Requiere: $contexto, $tipos, $padres, $accion, $metodo --}}
@php
    $colorActual = old('color', $contexto->color);
    $sinColor = $errors->any() ? old('sin_color') : ! $colorActual;
@endphp
<form method="POST" action="{{ $accion }}" novalidate>
    @csrf
    @if ($metodo !== 'POST')
        @method($metodo)
    @endif

    <div class="mb-3">
        <label for="nombre" class="form-label">Nombre</label>
        <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $contexto->nombre) }}"
               class="form-control @error('nombre') is-invalid @enderror" maxlength="255" required autofocus>
        @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label for="tipo" class="form-label">Tipo</label>
            <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                @foreach ($tipos as $tipo)
                    <option value="{{ $tipo->value }}" @selected(old('tipo', $contexto->tipo?->value) === $tipo->value)>{{ $tipo->etiqueta() }}</option>
                @endforeach
            </select>
            @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="contexto_padre_id" class="form-label">Dentro de <span class="text-secondary fw-normal">(opcional)</span></label>
            <select id="contexto_padre_id" name="contexto_padre_id" class="form-select @error('contexto_padre_id') is-invalid @enderror">
                <option value="">Ninguno (nivel superior)</option>
                @foreach ($padres as $id => $ruta)
                    <option value="{{ $id }}" @selected((string) old('contexto_padre_id', $contexto->contexto_padre_id) === (string) $id)>{{ $ruta }}</option>
                @endforeach
            </select>
            @error('contexto_padre_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="mb-4">
        <label for="color" class="form-label">Color <span class="text-secondary fw-normal">(opcional)</span></label>
        <div class="d-flex align-items-center gap-3">
            <input type="color" id="color" name="color" value="{{ $colorActual ?: '#c67139' }}" @disabled($sinColor)
                   class="form-control form-control-color @error('color') is-invalid @enderror">
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" id="sin_color" name="sin_color" value="1" @checked($sinColor)
                       onchange="document.getElementById('color').disabled = this.checked">
                <label class="form-check-label" for="sin_color">Sin color</label>
            </div>
        </div>
        @error('color') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-foco">Guardar</button>
        <a href="{{ route('contextos.index') }}" class="btn btn-foco-suave">Cancelar</a>
    </div>
</form>
