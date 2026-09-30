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

{{-- Modal de lectura: la nota completa al hacer clic en su tarjeta. Lo llena resources/js/notas.js con data-ver-nota. --}}
<dialog id="dialogo-ver-nota" class="dialogo dialogo-ver-nota" aria-labelledby="ver-nota-titulo">
    <div class="dialogo-cuerpo">
        <div class="dialogo-cab">
            <h2 class="dialogo-titulo" id="ver-nota-titulo" data-ver="titulo"></h2>
            <button type="button" class="dialogo-cerrar" data-cerrar-ver aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>

        <div class="ver-nota-contenido" data-ver="contenido" tabindex="0"></div>

        <dl class="ver-nota-datos">
            <div data-fila="destino"><dt><i class="bi bi-folder2" aria-hidden="true"></i> Destino</dt><dd data-ver="destino"></dd></div>
            <div data-fila="fecha"><dt><i class="bi bi-calendar-event" aria-hidden="true"></i> Fecha</dt><dd data-ver="fecha"></dd></div>
            <div data-fila="fijada"><dt><i class="bi bi-pin-angle-fill" aria-hidden="true"></i> Fijada</dt><dd>Sí</dd></div>
            <div data-fila="creada"><dt><i class="bi bi-clock" aria-hidden="true"></i> Creada</dt><dd data-ver="creada"></dd></div>
            <div data-fila="editada"><dt><i class="bi bi-pencil" aria-hidden="true"></i> Editada</dt><dd data-ver="editada"></dd></div>
        </dl>

        <div class="dialogo-pie-acciones">
            <button type="button" class="btn btn-foco-suave" data-cerrar-ver>Cerrar</button>
            <button type="button" class="btn btn-foco" data-editar-desde-ver><i class="bi bi-pencil" aria-hidden="true"></i> Editar</button>
        </div>
    </div>
</dialog>
