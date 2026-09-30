<?php

namespace App\Models;

use App\Enums\EstadoSesion;
use App\Enums\EstiloEstudio;
use App\Enums\TipoIntervalo;
use App\Models\Concerns\PerteneceAUsuario;
use Database\Factories\SesionEstudioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'contexto_id', 'tarea_id', 'tema', 'estilo', 'foco_seg', 'descanso_seg', 'descanso_largo_seg',
    'pomodoros_antes_largo', 'estado', 'iniciada_en', 'finalizada_en',
])]
class SesionEstudio extends Model
{
    /** @use HasFactory<SesionEstudioFactory> */
    use HasFactory, PerteneceAUsuario;

    protected $table = 'sesiones_estudio';

    protected function casts(): array
    {
        return [
            'estilo' => EstiloEstudio::class,
            'estado' => EstadoSesion::class,
            'iniciada_en' => 'datetime',
            'finalizada_en' => 'datetime',
            'foco_seg' => 'integer',
            'descanso_seg' => 'integer',
            'descanso_largo_seg' => 'integer',
            'pomodoros_antes_largo' => 'integer',
        ];
    }

    public function contexto(): BelongsTo
    {
        return $this->belongsTo(Contexto::class);
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class);
    }

    public function intervalos(): HasMany
    {
        return $this->hasMany(IntervaloEstudio::class, 'sesion_id');
    }

    #[Scope]
    protected function enCurso(Builder $query): Builder
    {
        return $query->where('estado', EstadoSesion::EnCurso);
    }

    /**
     * Filtros del historial: desde / hasta (Y-m-d, inclusivos) y contexto_id
     * (incluye los contextos hijos).
     *
     * @param  array{desde?: ?string, hasta?: ?string, contexto_id?: int|string|null}  $filtros
     */
    #[Scope]
    protected function filtrar(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->where('iniciada_en', '>=', $desde.' 00:00:00'))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->where('iniciada_en', '<=', $hasta.' 23:59:59'))
            ->when($filtros['contexto_id'] ?? null, function ($q, $contextoId) {
                $contexto = Contexto::find($contextoId);

                return $q->whereIn('contexto_id', $contexto ? $contexto->idsConDescendientes() : [$contextoId]);
            });
    }

    /** Intervalos de foco completos. Usa los intervalos ya cargados. */
    public function pomodorosCompletados(): int
    {
        return $this->intervalos->where('tipo', TipoIntervalo::Foco)->where('completado', true)->count();
    }

    public function pomodorosInterrumpidos(): int
    {
        return $this->intervalos->where('tipo', TipoIntervalo::Foco)->where('completado', false)->count();
    }

    public function segundosDe(TipoIntervalo $tipo): int
    {
        return (int) $this->intervalos->where('tipo', $tipo)->sum('duracion_seg');
    }

    /** Segundos que se habían previsto para ese tipo de intervalo. */
    public function segundosPlanificadosDe(TipoIntervalo $tipo): int
    {
        return (int) $this->intervalos->where('tipo', $tipo)->sum('planificado_seg');
    }
}
