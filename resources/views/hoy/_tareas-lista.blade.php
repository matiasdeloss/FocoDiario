{{-- Filas de la lista de Tareas abiertas: solo las abiertas. Las completadas salen de la lista al marcarlas. Requiere: $tareasAbiertas --}}
@foreach ($tareasAbiertas as $tarea)
    @include('hoy._tarea', ['tarea' => $tarea])
@endforeach
