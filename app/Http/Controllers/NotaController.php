<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltroNotasRequest;
use App\Http\Requests\MoverNotaRequest;
use App\Http\Requests\NotaRequest;
use App\Models\Contexto;
use App\Models\Nota;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotaController extends Controller
{
    public function index(FiltroNotasRequest $request): View
    {
        $filtro = $request->validated()['contexto'] ?? null;
        $contextoFiltro = null;

        $notas = Nota::query()->with('contexto')->ordenadas();

        if ($filtro === 'bandeja') {
            $notas->sinContexto();
        } elseif ($filtro !== null) {
            $contextoFiltro = Contexto::find($filtro);

            if ($contextoFiltro) {
                $notas->delContexto($contextoFiltro);
            } else {
                $filtro = null;
            }
        }

        $destinos = Contexto::opciones();

        return view('notas.index', [
            'notas' => $notas->get(),
            'destinos' => $destinos,
            'filtro' => $filtro,
            'contextoFiltro' => $contextoFiltro,
            'totalBandeja' => Nota::sinContexto()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('notas.crear', [
            'nota' => new Nota(['contexto_id' => $request->integer('contexto') ?: null]),
            'destinos' => Contexto::opciones(),
        ]);
    }

    public function store(NotaRequest $request): View|RedirectResponse
    {
        $nota = Nota::create($request->datosNota());

        if ($request->header('HX-Request') && $request->input('origen') === 'hoy') {
            return view('hoy._nota-rapida', ['guardada' => $nota->load('contexto')]);
        }

        return redirect()
            ->route($request->input('origen') === 'hoy' ? 'hoy' : 'notas.index')
            ->with('estado', 'Nota guardada.');
    }

    public function show(Nota $nota): RedirectResponse
    {
        return redirect()->route('notas.edit', $nota);
    }

    public function edit(Nota $nota): View
    {
        return view('notas.editar', [
            'nota' => $nota,
            'destinos' => Contexto::opciones(),
        ]);
    }

    public function update(NotaRequest $request, Nota $nota): RedirectResponse
    {
        $nota->update($request->datosNota());

        return redirect()->route('notas.index')->with('estado', 'Nota actualizada.');
    }

    public function destroy(Request $request, Nota $nota): Response|RedirectResponse
    {
        $nota->delete();

        if ($request->header('HX-Request')) {
            return response('');
        }

        return redirect()->route('notas.index')->with('estado', 'Nota eliminada.');
    }

    /** Fija o desfija la nota. Con HTMX devuelve solo la tarjeta actualizada. */
    public function fijar(Request $request, Nota $nota): View|RedirectResponse
    {
        $nota->update(['fijada' => ! $nota->fijada]);

        return $this->respuestaNota($request, $nota, $nota->fijada ? 'Nota fijada.' : 'Nota desfijada.');
    }

    /** Mueve la nota a otro contexto (o a la bandeja de entrada si no se elige ninguno). */
    public function mover(MoverNotaRequest $request, Nota $nota): View|RedirectResponse
    {
        $nota->update(['contexto_id' => $request->validated()['contexto_id'] ?? null]);

        return $this->respuestaNota($request, $nota, 'Nota movida.');
    }

    private function respuestaNota(Request $request, Nota $nota, string $mensaje): View|RedirectResponse
    {
        if ($request->header('HX-Request')) {
            return view('notas._nota', [
                'nota' => $nota->load('contexto'),
                'destinos' => Contexto::opciones(),
            ]);
        }

        return back()->with('estado', $mensaje);
    }
}
