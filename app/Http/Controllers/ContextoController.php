<?php

namespace App\Http\Controllers;

use App\Enums\TipoContexto;
use App\Http\Requests\ContextoRequest;
use App\Models\Contexto;
use App\Support\Aviso;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContextoController extends Controller
{
    public function index(): View
    {
        return view('contextos.index', ['arbol' => Contexto::arbol()]);
    }

    public function create(Request $request): View
    {
        return view('contextos.crear', $this->datosFormulario(new Contexto([
            'tipo' => TipoContexto::Materia,
            'contexto_padre_id' => $request->integer('padre') ?: null,
        ])));
    }

    public function store(ContextoRequest $request): RedirectResponse
    {
        Contexto::create($request->datosContexto());

        return redirect()->route('contextos.index')->with(Aviso::flash('Contexto creado.'));
    }

    public function show(Contexto $contexto): RedirectResponse
    {
        return redirect()->route('notas.index', ['contexto' => $contexto->id]);
    }

    public function edit(Contexto $contexto): View
    {
        return view('contextos.editar', $this->datosFormulario($contexto));
    }

    public function update(ContextoRequest $request, Contexto $contexto): RedirectResponse
    {
        $contexto->update($request->datosContexto());

        return redirect()->route('contextos.index')->with(Aviso::flash('Contexto actualizado.'));
    }

    public function destroy(Request $request, Contexto $contexto): Response|RedirectResponse
    {
        $contexto->delete();

        $mensaje = 'Contexto eliminado. Sus notas pasaron a la bandeja de entrada.';

        // La redirección de HTMX recarga la página: el aviso viaja en la sesión.
        if ($request->header('HX-Request')) {
            Aviso::guardar($mensaje);

            return response('')->header('HX-Redirect', route('contextos.index'));
        }

        return redirect()->route('contextos.index')->with(Aviso::flash($mensaje));
    }

    private function datosFormulario(Contexto $contexto): array
    {
        $padres = Contexto::opciones();

        // Al editar no se ofrece el propio contexto ni sus descendientes como padre.
        if ($contexto->exists) {
            $padres = $padres->except($contexto->idsConDescendientes());
        }

        return [
            'contexto' => $contexto,
            'tipos' => TipoContexto::cases(),
            'padres' => $padres,
        ];
    }
}
