{{-- Formulario compartido de crear y editar nota (páginas de respaldo sin JS). Requiere: $nota, $destinos, $accion, $metodo --}}
<form method="POST" action="{{ $accion }}" novalidate class="dialogo-form">
    @csrf
    @if ($metodo !== 'POST')
        @method($metodo)
    @endif

    @include('notas._campos', ['p' => 'pagina'])

    <div class="dialogo-botones">
        <button type="submit" class="btn btn-foco">Guardar</button>
        <a href="{{ route('notas.index') }}" class="btn btn-foco-suave">Cancelar</a>
    </div>
</form>
