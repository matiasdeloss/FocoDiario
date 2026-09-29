<?php

namespace App\Services\Recomendaciones;

use App\Enums\CategoriaRecomendacion;
use App\Enums\TipoRecomendacion;

/** Una recomendación lista para mostrar. Prioridad de 0 a 100: mayor número, más importante. */
final readonly class Recomendacion
{
    /**
     * @param  array<string, string>  $datos  Pares etiqueta y valor que se muestran junto al mensaje.
     */
    public function __construct(
        public string $titulo,
        public string $mensaje,
        public TipoRecomendacion $tipo,
        public string $icono,
        public int $prioridad,
        public ?Fuente $fuente = null,
        public array $datos = [],
        public ?CategoriaRecomendacion $categoria = null,
    ) {}
}
