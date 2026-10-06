<?php

namespace App\Http\Controllers;

use App\Models\Nota;
use App\Support\Busqueda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Búsqueda de notas del usuario para vincularlas a una tarea (el vínculo se guarda con la tarea, ver TareaController). */
class NotaTareaController extends Controller
{
    /** Hasta diez notas cuyo título o contenido contiene el texto (las más recientes si no hay texto). */
    public function buscar(Request $request): JsonResponse
    {
        $texto = trim((string) $request->query('q', ''));
        $patron = Busqueda::patron($texto);

        $notas = Nota::query()
            ->when($texto !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw(Busqueda::condicion('titulo'), [$patron])->orWhereRaw(Busqueda::condicion('contenido'), [$patron])))
            ->latest('updated_at')->latest('id')
            ->limit(10)
            ->get();

        return response()->json([
            'notas' => $notas->map(fn (Nota $nota) => ['id' => $nota->id, 'titulo' => $nota->tituloVisible()])->values(),
        ]);
    }
}
