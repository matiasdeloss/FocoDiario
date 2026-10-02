<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltroNotasRequest;
use App\Http\Requests\MoverNotaRequest;
use App\Http\Requests\NotaRequest;
use App\Enums\ColorNota;
use App\Models\Contexto;
use App\Models\Nota;
use App\Support\Aviso;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\Response;

class NotaController extends Controller
{
    public function index(FiltroNotasRequest $request): View
    {
        $datos = $request->validated();
        $filtro = $datos['contexto'] ?? null;
        $busqueda = trim((string) ($datos['q'] ?? ''));
        $color = isset($datos['color']) ? ColorNota::tryFrom($datos['color']) : null;
        $soloFijadas = (bool) ($datos['fijadas'] ?? false);
        $contextoFiltro = null;

        $notas = Nota::query()->with('contexto')->ordenadas();

        if ($busqueda !== '') {
            $patron = '%'.addcslashes($busqueda, '\\%_').'%';
            $notas->where(fn ($q) => $q->where('titulo', 'like', $patron)->orWhere('contenido', 'like', $patron));
        }

        if ($color) {
            $notas->where('color', $color->value);
        }

        if ($soloFijadas) {
            $notas->where('fijada', true);
        }

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
            'totalNotas' => Nota::count(),
            'totalFijadas' => Nota::where('fijada', true)->count(),
            'busqueda' => $busqueda,
            'colorFiltro' => $color,
            'soloFijadas' => $soloFijadas,
        ]);
    }

    public function create(Request $request): View
    {
        return view('notas.crear', [
            'nota' => new Nota([
                'contexto_id' => $request->integer('contexto') ?: null,
                'fecha' => Carbon::hasFormat((string) $request->query('fecha'), 'Y-m-d') ? $request->query('fecha') : null,
            ]),
            'destinos' => Contexto::opciones(),
        ]);
    }

    public function store(NotaRequest $request): Response|RedirectResponse|JsonResponse
    {
        $nota = Nota::create($request->datosNota());

        if ($request->expectsJson()) {
            Aviso::guardar('Nota guardada.');

            return response()->json(['ok' => true]);
        }

        if ($request->header('HX-Request') && $request->input('origen') === 'hoy') {
            $nota->load('contexto');

            return Aviso::enHtmx(
                view('hoy._nota-rapida', ['guardada' => $nota]),
                'Nota guardada '.($nota->contexto ? 'en '.$nota->contexto->rutaCompleta() : 'en la bandeja de entrada').'.',
            );
        }

        return redirect()
            ->route($request->input('origen') === 'hoy' ? 'hoy' : 'notas.index')
            ->with(Aviso::flash('Nota guardada.'));
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

    public function update(NotaRequest $request, Nota $nota): RedirectResponse|JsonResponse
    {
        $nota->update($request->datosNota());

        if ($request->expectsJson()) {
            Aviso::guardar('Nota actualizada.');

            return response()->json(['ok' => true]);
        }

        return redirect()->route('notas.index')->with(Aviso::flash('Nota actualizada.'));
    }

    public function destroy(Request $request, Nota $nota): Response|RedirectResponse
    {
        $nota->delete();

        if ($request->header('HX-Request')) {
            return response('');
        }

        return redirect()->route('notas.index')->with(Aviso::flash('Nota eliminada.'));
    }

    /** Fija o desfija la nota. Con HTMX devuelve solo la tarjeta actualizada. */
    public function fijar(Request $request, Nota $nota): Response|RedirectResponse
    {
        $nota->update(['fijada' => ! $nota->fijada]);

        return $this->respuestaNota($request, $nota, $nota->fijada ? 'Nota fijada.' : 'Nota desfijada.');
    }

    /** Mueve la nota a otro contexto (o a la bandeja de entrada si no se elige ninguno). */
    public function mover(MoverNotaRequest $request, Nota $nota): Response|RedirectResponse
    {
        $nota->update(['contexto_id' => $request->validated()['contexto_id'] ?? null]);

        return $this->respuestaNota($request, $nota, 'Nota movida.');
    }

    private function respuestaNota(Request $request, Nota $nota, string $mensaje): Response|RedirectResponse
    {
        if ($request->header('HX-Request')) {
            return Aviso::enHtmx(view('notas._nota', [
                'nota' => $nota->load('contexto'),
                'destinos' => Contexto::opciones(),
            ]), $mensaje);
        }

        return back()->with(Aviso::flash($mensaje));
    }
}
