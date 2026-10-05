{{-- Pista bajo el selector de color: "Usa el color de <contexto>" cuando "Sin color" está elegido y el contexto (o un ancestro) tiene color.
     Opcional: $visible (App\Support\ColorVisible heredado, para dibujarla ya desde el servidor). La actualiza color-heredado.js al
     cambiar el contexto o el color, leyendo data-color-heredado y data-color-origen de las opciones del selector. --}}
@php($visible = $visible ?? null)
<p class="color-pista" data-color-pista @unless ($visible) hidden @endunless aria-live="polite">
    <span class="color-pista-muestra" aria-hidden="true"
          @if ($visible) style="--pista-fondo: {{ $visible->fondo() }}; --pista-marca: {{ $visible->marca() }}" @endif></span>
    <span data-color-pista-texto>@if ($visible)Usa el color de {{ $visible->origen }}@endif</span>
</p>
