<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaRecomendacion;
use App\Services\Recomendaciones\CatalogoRecomendaciones;
use App\Services\Recomendaciones\MotorRecomendaciones;
use App\Services\Recomendaciones\Recomendacion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RecomendacionController extends Controller
{
    public function index(Request $request, MotorRecomendaciones $motor): View
    {
        $categoria = CategoriaRecomendacion::tryFrom((string) $request->query('categoria'));

        $paraTi = $motor->generar()
            ->when($categoria, fn ($c) => $c->filter(fn (Recomendacion $r) => $r->categoria === $categoria))
            ->values();

        // Las ideas rotan por día (semilla = fecha): no cambian al recargar. Sin filtro se muestran 8.
        $ideas = CatalogoRecomendaciones::delDia(now()->toDateString(), $categoria);
        if (! $categoria) {
            $ideas = $ideas->take(8)->values();
        }

        return view('recomendaciones.index', [
            'paraTi' => $paraTi,
            'ideas' => $ideas,
            'categoria' => $categoria,
            'categorias' => CategoriaRecomendacion::cases(),
            'alertas' => $paraTi->filter(fn (Recomendacion $r) => $r->tipo->value === 'alerta')->count(),
        ]);
    }
}
