<?php

namespace App\Http\Controllers;

use App\Http\Requests\BloqueTiempoRequest;
use App\Http\Requests\DiaRequest;
use App\Http\Requests\FiltroRegistroRequest;
use App\Models\BloqueTiempo;
use App\Models\Categoria;
use App\Models\Dia;
use App\Models\Tarea;
use App\Services\ResumenRegistro;
use Carbon\CarbonImmutable;
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
            'dia' => Dia::whereDate('fecha', $fecha)->first(),
            'categorias' => Categoria::orderBy('nombre')->get(),
            'tareas' => Tarea::abiertas()->orderBy('titulo')->get(['id', 'titulo']),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('registro.index');
    }

    public function store(BloqueTiempoRequest $request): RedirectResponse
    {
        BloqueTiempo::create($request->datosBloque());

        return redirect()->route('registro.index', ['fecha' => $request->validated('fecha')])
            ->with('estado', 'Bloque registrado.');
    }

    public function show(BloqueTiempo $registro): RedirectResponse
    {
        return redirect()->route('registro.edit', $registro);
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

    /** Guarda la hora de despertar y de dormir del día (un registro por fecha). */
    public function guardarDia(DiaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $fecha = CarbonImmutable::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();

        $desperto = ! empty($datos['desperto_a']) ? $fecha->setTimeFromTimeString($datos['desperto_a']) : null;
        $durmio = ! empty($datos['durmio_a']) ? $fecha->setTimeFromTimeString($datos['durmio_a']) : null;

        // Si se durmió a una hora igual o anterior a la de despertar, fue pasada la medianoche.
        if ($desperto && $durmio && $durmio->lte($desperto)) {
            $durmio = $durmio->addDay();
        }

        Dia::updateOrCreate(
            ['fecha' => $fecha->format('Y-m-d')],
            ['desperto_a' => $desperto, 'durmio_a' => $durmio],
        );

        return redirect()->route('registro.index', ['fecha' => $fecha->format('Y-m-d')])
            ->with('estado', 'Datos del día guardados.');
    }
}
