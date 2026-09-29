{{-- Tareas abiertas y las últimas completadas. Requiere: $tareasAbiertas, $tareasCompletadas, $totalPendientes --}}
<section id="hoy-tareas" class="hoy-tarjeta hoy-lateral" aria-labelledby="hoy-tareas-titulo"
         data-tareas data-url-crear="{{ route('tareas.store') }}" data-total-pendientes="{{ $totalPendientes }}">
    <div class="hoy-tarjeta-cab">
        <h2 class="hoy-tarjeta-titulo" id="hoy-tareas-titulo">Tareas abiertas</h2>
        <span class="hoy-meta" data-pendientes>{{ $totalPendientes }} {{ $totalPendientes === 1 ? 'pendiente' : 'pendientes' }}</span>
    </div>

    <ul class="hoy-lista" role="list" data-lista>
        @foreach ($tareasAbiertas as $tarea)
            @include('hoy._tarea', ['tarea' => $tarea])
        @endforeach
        @foreach ($tareasCompletadas as $tarea)
            @include('hoy._tarea', ['tarea' => $tarea])
        @endforeach
    </ul>

    <p class="hoy-vacio" data-vacio @if ($tareasAbiertas->isNotEmpty()) hidden @endif>No tenés tareas abiertas.</p>

    <form class="hoy-nueva" data-nueva-tarea action="{{ route('tareas.store') }}" method="POST" novalidate>
        <label for="hoy-nueva-tarea" class="hoy-solo-lector">Nueva tarea</label>
        <input type="text" id="hoy-nueva-tarea" name="titulo" class="hoy-entrada" placeholder="Nueva tarea…"
               maxlength="255" autocomplete="off" enterkeyhint="done">
        <button type="submit" class="hoy-boton-redondo" aria-label="Agregar tarea"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
    </form>

    <p class="hoy-mensaje" role="status" data-mensaje></p>

    <a href="{{ route('tareas.index') }}" class="hoy-enlace hoy-enlace-pie">ver todas las tareas</a>
</section>
