{{-- Filas de la lista de Tareas abiertas: primero las abiertas y al final las últimas completadas. Requiere: $tareasAbiertas, $tareasCompletadas --}}
@foreach ($tareasAbiertas as $tarea)
    @include('hoy._tarea', ['tarea' => $tarea])
@endforeach
@foreach ($tareasCompletadas as $tarea)
    @include('hoy._tarea', ['tarea' => $tarea])
@endforeach
