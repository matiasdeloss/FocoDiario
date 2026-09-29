{{-- Indicador discreto del autoguardado. El texto visible se oculta a los lectores de pantalla; solo los errores se anuncian. --}}
<div class="guardado" data-guardado data-estado="inactivo">
    <span class="guardado-texto" data-guardado-texto aria-hidden="true">Guardado</span>
    <button type="button" class="guardado-reintentar" data-guardado-reintentar hidden>Reintentar</button>
    <span class="visually-hidden" role="status" aria-live="polite" data-guardado-aviso></span>
</div>
