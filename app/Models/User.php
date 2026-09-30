<?php

namespace App\Models;

use App\Services\Cuentas\DatosIniciales;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Cuenta con email y contraseña, o invitado (es_invitado): un usuario anónimo atado a un navegador. */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Nombre con el que nace cada invitado. */
    public const NOMBRE_INVITADO = 'Invitado';

    protected static function booted(): void
    {
        // Cada usuario (invitado o cuenta) arranca con lo mínimo para usar la app: las columnas del tablero.
        static::created(fn (User $usuario) => app(DatosIniciales::class)->crear($usuario));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'es_invitado' => 'boolean',
            'ultimo_uso_en' => 'datetime',
        ];
    }

    /** Invitado nuevo: sin email ni contraseña. */
    public static function crearInvitado(): self
    {
        $invitado = new self(['name' => self::NOMBRE_INVITADO]);
        $invitado->es_invitado = true;
        $invitado->ultimo_uso_en = now();
        $invitado->save();

        return $invitado;
    }

    /** Nombre para la barra: el propio o, si no tiene, el email. */
    public function nombreVisible(): string
    {
        return $this->es_invitado ? self::NOMBRE_INVITADO : ($this->name ?: (string) $this->email);
    }
}
