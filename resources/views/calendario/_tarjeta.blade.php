{{-- Tarjeta simple del panel "Por ubicar". Requiere: $t (datos de la tarjeta). Opcional: $nueva. --}}
@php
    $meta = [
        'tarea' => ['etiqueta' => 'Tarea', 'articulo' => 'la tarea', 'fecha' => 'Fecha límite', 'campo' => 'date'],
        'recordatorio' => ['etiqueta' => 'Recordatorio', 'articulo' => 'el recordatorio', 'fecha' => 'Fecha y hora del aviso', 'campo' => 'datetime-local'],
        'nota' => ['etiqueta' => 'Nota', 'articulo' => 'la nota', 'fecha' => 'Fecha', 'campo' => 'date'],
    ][$t['tipo']];
    $idFecha = 'tarj-fecha-'.$t['tipo'].'-'.$t['id'];
@endphp
<li class="tarj tipo-{{ $t['tipo'] }}" data-tarjeta data-tipo="{{ $t['tipo'] }}" data-id="{{ $t['id'] }}"
    data-editar="{{ $t['editar'] }}" @if (! empty($nueva)) data-nueva="1" @endif>
    <div class="tarj-cabeza" title="Arrastrá para ubicarla en el calendario">
        <i class="bi bi-grip-vertical tarj-asa" aria-hidden="true"></i>
        <span class="tarj-tipo"><span class="tarj-punto" aria-hidden="true"></span>{{ $meta['etiqueta'] }}</span>
        @if (! empty($t['contexto']))
            <span class="tarj-contexto {{ $t['contexto']['clase'] ?? 'tarj-contexto-neutro' }}" title="{{ $t['contexto']['ruta'] }}">
                <span class="tarj-contexto-punto" aria-hidden="true"></span>
                <span class="visually-hidden">Contexto:</span><span class="tarj-contexto-texto">{{ $t['contexto']['nombre'] }}</span>
            </span>
        @endif
        <time class="tarj-creada" datetime="{{ $t['creada']->toDateString() }}" title="Fecha de creación">{{ $t['creada']->format('d/m/Y') }}</time>
    </div>
    <input type="text" class="tarj-campo tarj-titulo" data-campo="titulo" value="{{ $t['titulo'] }}" maxlength="255"
           placeholder="Título" aria-label="Título de {{ $meta['articulo'] }}" autocomplete="off" data-valor="{{ $t['titulo'] }}">
    <textarea class="tarj-campo tarj-comentario" data-campo="comentario" rows="1" maxlength="5000"
              placeholder="Agregar un comentario" aria-label="Comentario de {{ $meta['articulo'] }}" data-valor="{{ $t['comentario'] }}">{{ $t['comentario'] }}</textarea>
    <div class="tarj-pie">
        <div class="tarj-fecha">
            <label class="visually-hidden" for="{{ $idFecha }}">{{ $meta['fecha'] }} de {{ $meta['articulo'] }}{{ $t['titulo'] !== '' ? ': '.$t['titulo'] : '' }}</label>
            <input type="{{ $meta['campo'] }}" class="tarj-fecha-campo" id="{{ $idFecha }}"
                   data-fecha-tarjeta title="{{ $meta['fecha'] }}: al elegirla pasa al calendario">
        </div>
        <span class="tarj-guardado" data-guardado role="status" aria-live="polite"></span>
        {{-- Con JS despliega los demás campos dentro de la tarjeta; sin JS, lleva al formulario completo. --}}
        <a href="{{ $t['editar'] }}" class="btn-icono tarj-accion" data-editar-tarjeta aria-expanded="false"
           aria-controls="tarj-extra-{{ $t['tipo'] }}-{{ $t['id'] }}" aria-label="Editar todo de {{ $meta['articulo'] }}" title="Editar todo">
            <i class="bi bi-sliders2" aria-hidden="true"></i>
        </a>
        <button type="button" class="btn-icono tarj-borrar" data-borrar aria-label="Eliminar {{ $meta['articulo'] }}" title="Eliminar">
            <i class="bi bi-trash" aria-hidden="true"></i>
        </button>
    </div>
    <div class="tarj-extra" id="tarj-extra-{{ $t['tipo'] }}-{{ $t['id'] }}" data-extra hidden></div>
</li>
