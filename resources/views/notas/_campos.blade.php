{{-- Campos compartidos por el modal y las páginas de crear/editar nota.
     Requiere: $nota, $destinos, $p (prefijo de ids). En el modal los valores los pone notas.js. --}}
@php($colorActual = old('color', $nota->color?->value ?? ''))
@php($coloresCtx = $colores ?? \App\Support\ColoresDeContexto::delUsuario())
<div>
    <label for="{{ $p }}-titulo" class="dialogo-etiqueta">Título <span class="dialogo-opcional">(opcional)</span></label>
    <input type="text" id="{{ $p }}-titulo" name="titulo" value="{{ old('titulo', $nota->titulo) }}" maxlength="255" autocomplete="off"
           class="form-control @error('titulo') is-invalid @enderror" aria-describedby="{{ $p }}-error-titulo" @error('titulo') aria-invalid="true" @enderror>
    <div class="dialogo-error" id="{{ $p }}-error-titulo" data-error="titulo">@error('titulo'){{ $message }}@enderror</div>
</div>

<div>
    <label for="{{ $p }}-contenido" class="dialogo-etiqueta">Nota</label>
    <textarea id="{{ $p }}-contenido" name="contenido" rows="6" maxlength="5000"
              class="form-control @error('contenido') is-invalid @enderror" aria-describedby="{{ $p }}-error-contenido" @error('contenido') aria-invalid="true" @enderror>{{ old('contenido', $nota->contenido) }}</textarea>
    <div class="dialogo-error" id="{{ $p }}-error-contenido" data-error="contenido">@error('contenido'){{ $message }}@enderror</div>
</div>

<div class="dialogo-fila">
    <div class="dialogo-campo">
        <label for="{{ $p }}-contexto" class="dialogo-etiqueta">Destino</label>
        <select id="{{ $p }}-contexto" name="contexto_id" class="form-select @error('contexto_id') is-invalid @enderror" aria-describedby="{{ $p }}-error-contexto_id">
            <option value="">Bandeja de entrada</option>
            @foreach ($destinos as $id => $ruta)
                @php($heredado = $coloresCtx->visible($id))
                <option value="{{ $id }}" @selected((string) old('contexto_id', $nota->contexto_id) === (string) $id)
                        @if ($heredado) data-color-heredado="{{ $heredado->color->clave() }}" data-color-origen="{{ $heredado->origen }}" @endif>{{ $ruta }}</option>
            @endforeach
        </select>
        <div class="dialogo-error" id="{{ $p }}-error-contexto_id" data-error="contexto_id">@error('contexto_id'){{ $message }}@enderror</div>
    </div>
    <div class="dialogo-campo">
        <label for="{{ $p }}-fecha" class="dialogo-etiqueta">Fecha <span class="dialogo-opcional">(opcional)</span></label>
        <input type="date" id="{{ $p }}-fecha" name="fecha" value="{{ old('fecha', $nota->fecha?->format('Y-m-d')) }}"
               class="form-control @error('fecha') is-invalid @enderror" aria-describedby="{{ $p }}-error-fecha">
        <div class="dialogo-error" id="{{ $p }}-error-fecha" data-error="fecha">@error('fecha'){{ $message }}@enderror</div>
    </div>
</div>

<fieldset class="dialogo-grupo">
    <legend>Color</legend>
    <div class="notas-colores">
        <label class="nota-color" title="Sin color (usa el del contexto)">
            <input type="radio" name="color" value="" @checked($colorActual === '')>
            <span class="nota-color-punto nota-color-ninguno" aria-hidden="true"></span>
            <span class="visually-hidden">Sin color (usa el del contexto)</span>
        </label>
        @foreach (\App\Enums\ColorActividad::cases() as $c)
            <label class="nota-color" title="{{ $c->etiqueta() }}" style="--nota-fondo: {{ $c->fondo() }}; --nota-marca: {{ $c->marca() }}">
                <input type="radio" name="color" value="{{ $c->value }}" @checked($colorActual === $c->value)>
                <span class="nota-color-punto" aria-hidden="true"></span>
                <span class="visually-hidden">{{ $c->etiqueta() }}</span>
            </label>
        @endforeach
    </div>
    @include('partials.pista-color', ['visible' => $colorActual === '' && $nota->contexto_id !== null ? $coloresCtx->visible((int) old('contexto_id', $nota->contexto_id)) : null])
    <div class="dialogo-error" data-error="color">@error('color'){{ $message }}@enderror</div>
</fieldset>

<div class="form-check">
    <input type="hidden" name="fijada" value="0">
    <input type="checkbox" class="form-check-input" id="{{ $p }}-fijada" name="fijada" value="1" @checked(old('fijada', $nota->fijada))>
    <label class="form-check-label" for="{{ $p }}-fijada">Fijar arriba en el listado</label>
</div>
