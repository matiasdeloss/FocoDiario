{{-- Formulario compartido de crear y editar contexto. Requiere: $contexto, $tipos, $padres, $accion, $metodo --}}
@php
    $colorActual = strtolower((string) old('color', $contexto->color));
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

    <fieldset class="mb-4">
        <legend class="form-label">Color <span class="text-secondary fw-normal">(opcional)</span></legend>
        <div class="paleta-actividades">
            <label class="paleta-opcion paleta-sin-color" title="Sin color (usa el del contexto padre)">
                <input type="radio" name="color" value="" @checked($colorActual === '')>
                <span class="paleta-nombre">Sin color (usa el del contexto padre)</span>
            </label>
            @foreach (\App\Enums\ColorActividad::cases() as $color)
                <label class="paleta-opcion {{ $color->clase() }}">
                    <input type="radio" name="color" value="{{ $color->value }}" @checked($colorActual === $color->value)>
                    <span class="paleta-punto" aria-hidden="true"></span>
                    <span class="paleta-nombre">{{ $color->etiqueta() }}</span>
                </label>
            @endforeach
        </div>
        @error('color') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </fieldset>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-foco">Guardar</button>
        <a href="{{ route('contextos.index') }}" class="btn btn-foco-suave">Cancelar</a>
    </div>
</form>
