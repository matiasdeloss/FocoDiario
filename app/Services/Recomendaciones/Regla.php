<?php

namespace App\Services\Recomendaciones;

interface Regla
{
    /** @return list<Recomendacion> */
    public function evaluar(ContextoRecomendacion $contexto): array;
}
