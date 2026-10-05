<?php

namespace App\Models;

use App\Enums\TipoCaja;
use App\Enums\ZonaSemana;
use App\Models\Concerns\PerteneceAUsuario;
use Carbon\CarbonInterface;
use Database\Factories\CajaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Caja de la agenda: bloque de texto o lista sobre la hoja de un día, o caja de notas/pendiente de una semana.
 * Las clases repetidas y los parciales de la etapa 2 podrán colgar de esta misma tabla (nuevas columnas o tablas
 * que la referencien) sin cambiar lo que ya hay.
 */
#[Fillable([
    'fecha', 'semana', 'zona', 'contexto_id', 'titulo', 'tipo', 'contenido', 'items',
    'hora_inicio', 'hora_fin', 'x', 'y', 'ancho', 'alto', 'orden', 'hecha', 'borde_grosor', 'borde_color',
])]
class Caja extends Model
{
    /** @use HasFactory<CajaFactory> */
    use HasFactory, PerteneceAUsuario;

    public const COLUMNAS = 12;

    public const MAX_FILA = 2000;

    public const MAX_ALTO = 100;

    protected $table = 'cajas';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'semana' => 'date',
            'zona' => ZonaSemana::class,
            'tipo' => TipoCaja::class,
            'items' => 'array',
            'hecha' => 'boolean',
            'x' => 'integer',
            'y' => 'integer',
            'ancho' => 'integer',
            'alto' => 'integer',
            'orden' => 'integer',
            'borde_grosor' => 'integer',
        ];
    }

    public function contexto(): BelongsTo
    {
        return $this->belongsTo(Contexto::class, 'contexto_id');
    }

    /** Hora de inicio como "HH:MM" (la base guarda HH:MM:SS). */
    protected function horaInicio(): Attribute
    {
        return $this->horaAttribute();
    }

    protected function horaFin(): Attribute
    {
        return $this->horaAttribute();
    }

    private function horaAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (?string $valor) => $valor === null || $valor === '' ? null : substr($valor, 0, 5),
            set: fn (?string $valor) => $valor === null || $valor === '' ? null : substr($valor, 0, 5).':00',
        );
    }

    #[Scope]
    protected function delDia(Builder $query, CarbonInterface|string $fecha): Builder
    {
        return $query->whereDate('fecha', Carbon::parse($fecha)->toDateString());
    }

    /** Cajas de Notas y Pendiente de la semana que empieza en ese lunes. */
    #[Scope]
    protected function deLaSemana(Builder $query, CarbonInterface|string $lunes): Builder
    {
        return $query->whereDate('semana', Carbon::parse($lunes)->toDateString());
    }

    /** Cajas de los días entre dos fechas (ambas incluidas). */
    #[Scope]
    protected function entreFechas(Builder $query, CarbonInterface|string $desde, CarbonInterface|string $hasta): Builder
    {
        return $query->whereDate('fecha', '>=', Carbon::parse($desde)->toDateString())
            ->whereDate('fecha', '<=', Carbon::parse($hasta)->toDateString());
    }

    #[Scope]
    protected function hechas(Builder $query): Builder
    {
        return $query->where('hecha', true);
    }

    #[Scope]
    protected function pendientes(Builder $query): Builder
    {
        return $query->where('hecha', false);
    }

    /** Orden de lectura en la hoja: de arriba hacia abajo y de izquierda a derecha. */
    #[Scope]
    protected function enOrdenDeLectura(Builder $query): Builder
    {
        return $query->orderBy('y')->orderBy('x')->orderBy('id');
    }

    /** Orden del planner: primero las que tienen hora (por hora), después las demás por posición. */
    #[Scope]
    protected function enOrdenDePlanner(Builder $query): Builder
    {
        return $query->orderByRaw('hora_inicio is null')->orderBy('hora_inicio')->orderBy('y')->orderBy('x')->orderBy('id');
    }

    /** Título para mostrar en el planner. */
    public function tituloVisible(): string
    {
        $titulo = trim((string) $this->titulo);

        return $titulo !== '' ? $titulo : 'Sin título';
    }

    /** "18:00" o "18:00–19:30", o null si no tiene hora. */
    public function horaTexto(): ?string
    {
        if ($this->hora_inicio === null) {
            return null;
        }

        return $this->hora_fin === null ? $this->hora_inicio : $this->hora_inicio.'–'.$this->hora_fin;
    }

    /** Ítems de la lista normalizados a [{texto, hecho}]. */
    public function itemsLista(): array
    {
        return array_values(array_map(
            fn ($item) => ['texto' => (string) ($item['texto'] ?? ''), 'hecho' => (bool) ($item['hecho'] ?? false)],
            is_array($this->items) ? $this->items : [],
        ));
    }
}
