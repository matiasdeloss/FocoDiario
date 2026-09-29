{{-- Fila de una lista con casillas. Requiere: $item (['texto', 'hecho']) --}}
<li class="caja-item {{ $item['hecho'] ? 'es-tildado' : '' }}" data-item>
    <input type="checkbox" class="caja-check" data-item-check aria-label="Tildar ítem" @checked($item['hecho'])>
    <textarea class="caja-item-texto" data-item-texto rows="1" maxlength="500" aria-label="Texto del ítem" placeholder="Ítem" enterkeyhint="next">{{ $item['texto'] }}</textarea>
    <button type="button" class="caja-item-quitar" data-accion="quitar-item" aria-label="Quitar ítem" title="Quitar ítem"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
</li>
