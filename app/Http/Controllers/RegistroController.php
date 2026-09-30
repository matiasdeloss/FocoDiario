<?php

namespace App\Http\Controllers;

use App\Http\Requests\BloqueTiempoRequest;
use App\Http\Requests\FiltroRegistroRequest;
use App\Models\BloqueTiempo;
use App\Models\Categoria;
use App\Models\Tarea;
use App\Services\ResumenRegistro;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RegistroController extends Controller
{
    public function index(FiltroRegistroRequest $request): View
    {
        $fecha = $request->dia();

        $bloques = BloqueTiempo::with(['categoria', 'tarea'])
            ->whereBetween('inicio', [$fecha->startOfDay(), $fecha->endOfDay()])
            ->orderBy('inicio')
            ->get();

        return view('registro.index', [
            'fecha' => $fecha,
            'bloques' => $bloques,
            'resumen' => ResumenRegistro::deBloques($bloques),
            'categorias' => Categoria::orderBy('nombre')->get(),
            'tareas' => Tarea::abiertas()->orderBy('titulo')->get(['id', 'titulo']),
        ]);
    }

    public function store(BloqueTiempoRequest $request): RedirectResponse
    {
        BloqueTiempo::create($request->datosBloque());

        return redirect()->route('registro.index', ['fecha' => $request->validated('fecha')])
            ->with('estado', 'Bloque registrado.');
    }

    public function edit(BloqueTiempo $registro): View
    {
        return view('registro.editar', [
            'bloque' => $registro,
            'categorias' => Categoria::orderBy('nombre')->get(),
            'tareas' => Tarea::abiertas()
                ->when($registro->tarea_id, fn ($consulta, $id) => $consulta->orWhere('id', $id))
                ->orderBy('titulo')
                ->get(['id', 'titulo']),
        ]);
    }

    public function update(BloqueTiempoRequest $request, BloqueTiempo $registro): RedirectResponse
    {
        $registro->update($request->datosBloque());

        return redirect()->route('registro.index', ['fecha' => $request->validated('fecha')])
            ->with('estado', 'Bloque actualizado.');
    }

    public function destroy(BloqueTiempo $registro): RedirectResponse
    {
        $registro->delete();

        return redirect()->route('registro.index', ['fecha' => $registro->inicio->format('Y-m-d')])
            ->with('estado', 'Bloque eliminado.');
    }
}
