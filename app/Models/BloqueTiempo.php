<?php

namespace App\Models;

use App\Enums\OrigenBloque;
use Database\Factories\BloqueTiempoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('bloques_tiempo')]
#[Fillable(['categoria_id', 'tarea_id', 'inicio', 'fin', 'origen', 'concentracion'])]
class BloqueTiempo extends Model
{
    /** @use HasFactory<BloqueTiempoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'origen' => OrigenBloque::class,
            'concentracion' => 'integer',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class);
    }

    public function duracionEnMinutos(): int
    {
        return max(0, (int) $this->inicio->diffInMinutes($this->fin, true));
    }
}
