<?php

namespace App\Models;

use App\Enums\TipoIntervalo;
use App\Models\Concerns\PerteneceAUsuario;
use Database\Factories\IntervaloEstudioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sesion_id', 'tipo', 'clave', 'inicio', 'fin', 'planificado_seg', 'pausado_seg',
    'duracion_seg', 'completado', 'bloque_tiempo_id',
])]
class IntervaloEstudio extends Model
{
    /** @use HasFactory<IntervaloEstudioFactory> */
    use HasFactory, PerteneceAUsuario;

    protected $table = 'intervalos_estudio';

    protected function casts(): array
    {
        return [
            'tipo' => TipoIntervalo::class,
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'planificado_seg' => 'integer',
            'pausado_seg' => 'integer',
            'duracion_seg' => 'integer',
            'completado' => 'boolean',
        ];
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(SesionEstudio::class, 'sesion_id');
    }

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(BloqueTiempo::class, 'bloque_tiempo_id');
    }
}
