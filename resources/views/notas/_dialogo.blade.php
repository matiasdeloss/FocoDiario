{{-- Modal de crear y editar nota (mejora progresiva: sin JS, los enlaces llevan a las páginas de siempre). Lo maneja resources/js/notas.js. --}}
<dialog id="dialogo-nota" class="dialogo" aria-labelledby="dialogo-nota-titulo" data-url-crear="{{ route('notas.store') }}">
    <form class="dialogo-cuerpo dialogo-form" method="POST" action="{{ route('notas.store') }}" novalidate data-form-nota>
        <div class="dialogo-cab">
            <h2 class="dialogo-titulo" id="dialogo-nota-titulo" data-titulo-dialogo>Nueva nota</h2>
            <button type="button" class="dialogo-cerrar" data-cerrar-modal aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>

        <div class="dialogo-avisos dialogo-error" data-avisos role="alert"></div>

        @include('notas._campos', ['p' => 'modal', 'nota' => new \App\Models\Nota(), 'destinos' => $destinos])

        <div class="dialogo-botones">
            <button type="submit" class="btn btn-foco" data-enviar>Guardar</button>
            <button type="button" class="btn btn-foco-suave" data-cerrar-modal>Cancelar</button>
        </div>
    </form>
</dialog>
