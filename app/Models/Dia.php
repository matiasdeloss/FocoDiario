<?php

namespace App\Models;

use Database\Factories\DiaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fecha', 'desperto_a', 'durmio_a'])]
class Dia extends Model
{
    /** @use HasFactory<DiaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'desperto_a' => 'datetime',
            'durmio_a' => 'datetime',
        ];
    }

    /** Minutos despierto, o null si falta la hora de despertar o de dormir. */
    public function minutosDespierto(): ?int
    {
        if ($this->desperto_a === null || $this->durmio_a === null) {
            return null;
        }

        return max(0, (int) $this->desperto_a->diffInMinutes($this->durmio_a, true));
    }
}
