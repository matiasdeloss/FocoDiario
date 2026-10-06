<?php

namespace App\Http\Controllers;

use App\Models\Nota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Búsqueda de notas del usuario para vincularlas a una tarea (el vínculo se guarda con la tarea, ver TareaController). */
class NotaTareaController extends Controller
{
    /** Hasta diez notas cuyo título o contenido contiene el texto (las más recientes si no hay texto). */
    public function buscar(Request $request): JsonResponse
    {
        $texto = trim((string) $request->query('q', ''));
        $patron = '%'.addcslashes($texto, '\\%_').'%';

        $notas = Nota::query()
            ->when($texto !== '', fn ($q) => $q->where(fn ($w) => $w->where('titulo', 'like', $patron)->orWhere('contenido', 'like', $patron)))
            ->latest('updated_at')->latest('id')
            ->limit(10)
            ->get();

        return response()->json([
            'notas' => $notas->map(fn (Nota $nota) => ['id' => $nota->id, 'titulo' => $nota->tituloVisible()])->values(),
        ]);
    }
}
