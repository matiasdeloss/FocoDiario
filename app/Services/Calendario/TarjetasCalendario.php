<?php

namespace App\Services\Calendario;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Models\ColumnaTablero;
use App\Models\Contexto;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Support\ColoresDeContexto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Tarjetas simples del panel "Por ubicar": tareas, recordatorios y notas sin fecha.
 * Todas se describen con la misma forma de arreglo para la vista y el JS.
 */
class TarjetasCalendario
{
    public const TIPOS = ['tarea', 'recordatorio', 'nota'];

    public const POR_PAGINA = 20;

    /** Formatos de fecha aceptados al crear o ubicar una tarjeta. */
    public const FORMATOS_FECHA = ['Y-m-d', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'];

    public function buscar(string $tipo, int $id): Model
    {
        return match ($tipo) {
            'tarea' => Tarea::findOrFail($id),
            'recordatorio' => Recordatorio::findOrFail($id),
            'nota' => Nota::findOrFail($id),
            default => abort(404),
        };
    }

    /** Crea una tarjeta vacía; con fecha ya queda ubicada en el calendario. */
    public function crear(string $tipo, ?string $fecha): Model
    {
        $dia = $fecha ? substr($fecha, 0, 10) : null;

        return match ($tipo) {
            'tarea' => Tarea::create([
                'titulo' => '',
                'prioridad' => PrioridadTarea::Media,
                'estado' => EstadoTarea::Pendiente,
                'fecha_limite' => $dia,
            ]),
            'recordatorio' => Recordatorio::create([
                'mensaje' => '',
                'recordar_en' => $fecha ? $this->fechaHora($fecha) : null,
            ]),
            'nota' => Nota::create(['contenido' => '', 'fecha' => $dia]),
        };
    }

    /**
     * Guarda solo los campos enviados: título y comentario en todas; prioridad, estado y contexto en tareas;
     * color, materia y fijada en notas; la tarea vinculada en recordatorios.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Model $tarjeta, array $datos): void
    {
        $campos = [];

        if (array_key_exists('titulo', $datos)) {
            $titulo = trim((string) $datos['titulo']);

            if ($tarjeta instanceof Recordatorio) {
                $campos['mensaje'] = $titulo;
            } else {
                $campos['titulo'] = $tarjeta instanceof Nota && $titulo === '' ? null : $titulo;
            }
        }

        if (array_key_exists('comentario', $datos)) {
            if ($tarjeta instanceof Nota) {
                $campos['contenido'] = (string) $datos['comentario'];
            } else {
                $campos['descripcion'] = $datos['comentario'];
            }
        }

        if ($tarjeta instanceof Tarea) {
            if (array_key_exists('prioridad', $datos)) {
                $campos['prioridad'] = $datos['prioridad'];
            }

            if (array_key_exists('completada', $datos)) {
                $campos['estado'] = $datos['completada'] ? EstadoTarea::Completada : EstadoTarea::Pendiente;
                // Destildar vuelve a la columna previa; si además se elige un estado, manda ese (ver más abajo).
                $tarjeta->reabrirEnColumnaPrevia = ! array_key_exists('estado', $datos);
            }

            if (array_key_exists('estado', $datos)) {
                $campos['estado'] = $datos['estado'];
                $pedido = EstadoTarea::tryFrom((string) $datos['estado']);

                if ($pedido !== null && ColumnaTablero::faltaCategoria($pedido, $tarjeta->columna?->tablero_id)) {
                    throw ValidationException::withMessages(['estado' => ColumnaTablero::mensajeSinCategoria($pedido)]);
                }
            }

            if (array_key_exists('contexto_id', $datos)) {
                $campos['contexto_id'] = $datos['contexto_id'];
            }
        }

        if ($tarjeta instanceof Nota) {
            foreach (['color', 'contexto_id', 'fijada'] as $campo) {
                if (array_key_exists($campo, $datos)) {
                    $campos[$campo] = $datos[$campo];
                }
            }
        }

        if ($tarjeta instanceof Recordatorio && array_key_exists('tarea_id', $datos)) {
            $campos['tarea_id'] = $datos['tarea_id'];
        }

        $tarjeta->update($campos);
    }

    /** Fecha y hora de un recordatorio: si solo viene el día, a las 09:00. */
    public function fechaHora(string $valor): Carbon
    {
        if (strlen($valor) === 10) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $valor.' 09:00:00');
        }

        return Carbon::parse($valor);
    }

    /**
     * Lo que el panel necesita para mostrar el contexto de cada tarjeta (ruta completa y color efectivo de todos los
     * contextos): se arma una vez y se reutiliza en toda la lista, sin una consulta por tarjeta.
     *
     * @return array{rutas: Collection<int, string>, colores: ColoresDeContexto}
     */
    public function contextosDelPanel(): array
    {
        return ['rutas' => Contexto::opciones(), 'colores' => ColoresDeContexto::delUsuario()];
    }

    /**
     * Con `$contextos` (ver contextosDelPanel) agrega el contexto de la tarjeta: tareas y notas lo tienen, los recordatorios no.
     *
     * @param  array{rutas: Collection<int, string>, colores: ColoresDeContexto}|null  $contextos
     * @return array<string, mixed>
     */
    public function datos(Model $tarjeta, ?array $contextos = null): array
    {
        $datos = $this->datosBase($tarjeta);

        if ($contextos !== null) {
            $id = $tarjeta instanceof Tarea || $tarjeta instanceof Nota ? $tarjeta->contexto_id : null;
            $datos['contexto'] = $id === null || ! $contextos['rutas']->has($id) ? null : [
                'nombre' => $tarjeta->contexto?->nombre,
                'ruta' => $contextos['rutas'][$id],
                'clase' => $contextos['colores']->clase($id),
            ];
        }

        return $datos;
    }

    /** @return array<string, mixed> */
    private function datosBase(Model $tarjeta): array
    {
        return match (true) {
            $tarjeta instanceof Tarea => [
                'tipo' => 'tarea',
                'id' => $tarjeta->id,
                'titulo' => $tarjeta->titulo,
                'comentario' => $tarjeta->descripcion,
                'fecha' => $tarjeta->fecha_limite?->toDateString(),
                'creada' => $tarjeta->created_at,
                'editar' => route('tareas.edit', $tarjeta),
                'bloqueada' => $tarjeta->estado === EstadoTarea::Completada,
            ],
            $tarjeta instanceof Recordatorio => [
                'tipo' => 'recordatorio',
                'id' => $tarjeta->id,
                'titulo' => $tarjeta->mensaje,
                'comentario' => $tarjeta->descripcion,
                'fecha' => $tarjeta->recordar_en?->format('Y-m-d\TH:i'),
                'creada' => $tarjeta->created_at,
                'editar' => route('recordatorios.edit', $tarjeta),
                'bloqueada' => $tarjeta->avisado_en !== null,
            ],
            $tarjeta instanceof Nota => [
                'tipo' => 'nota',
                'id' => $tarjeta->id,
                'titulo' => $tarjeta->tituloVisible(),
                'comentario' => $tarjeta->contenido,
                'fecha' => $tarjeta->fecha?->toDateString(),
                'creada' => $tarjeta->created_at,
                'editar' => route('notas.edit', $tarjeta),
                'bloqueada' => false,
            ],
        };
    }

    /** HTML de una tarjeta del panel. */
    public function html(Model $tarjeta, bool $nueva = false, ?array $contextos = null): string
    {
        return view('calendario._tarjeta', ['t' => $this->datos($tarjeta, $contextos ?? $this->contextosDelPanel()), 'nueva' => $nueva])->render();
    }

    /**
     * Las más recientes de un tipo que están sin ubicar: una página de POR_PAGINA + 1
     * (el sobrante indica que hay más).
     *
     * @param  list<int>  $excluir  ids que el panel ya muestra
     * @return Collection<int, Model>
     */
    public function pagina(string $tipo, array $excluir = []): Collection
    {
        $consulta = match ($tipo) {
            'tarea' => Tarea::abiertas()->with('contexto:id,nombre')->whereNull('fecha_limite'),
            'recordatorio' => Recordatorio::pendientes()->sinFecha(),
            'nota' => Nota::with('contexto:id,nombre')->whereNull('fecha'),
        };

        return $consulta
            ->when($excluir !== [], fn ($q) => $q->whereNotIn('id', $excluir))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::POR_PAGINA + 1)
            ->get();
    }

    /**
     * Contenido inicial del panel: una consulta por tipo, mezcladas por fecha de creación.
     *
     * @return array{tarjetas: Collection<int, array<string, mixed>>, hayMas: array<string, bool>}
     */
    public function panel(): array
    {
        $tarjetas = collect();
        $hayMas = [];
        $contextos = $this->contextosDelPanel();

        foreach (self::TIPOS as $tipo) {
            $filas = $this->pagina($tipo);
            $hayMas[$tipo] = $filas->count() > self::POR_PAGINA;
            $tarjetas = $tarjetas->concat($filas->take(self::POR_PAGINA)->map(fn (Model $m) => $this->datos($m, $contextos)));
        }

        return [
            'tarjetas' => $tarjetas->sortByDesc(fn (array $t) => $t['creada']->timestamp)->values(),
            'hayMas' => $hayMas,
        ];
    }
}
