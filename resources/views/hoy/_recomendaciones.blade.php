<section class="tarjeta p-4 mb-3" aria-labelledby="recomendaciones-titulo">
    <h2 class="tarjeta-titulo d-flex justify-content-between" id="recomendaciones-titulo">
        Recomendaciones para ahora
        <a href="{{ route('recomendaciones.index') }}" class="text-decoration-none text-lowercase fw-normal">ver todas</a>
    </h2>
    @forelse ($recomendaciones as $recomendacion)
        @include('recomendaciones._item', ['recomendacion' => $recomendacion])
    @empty
        <p class="estado-vacio">Por ahora no hay recomendaciones.</p>
    @endforelse
</section>
