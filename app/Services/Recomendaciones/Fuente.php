<?php

namespace App\Services\Recomendaciones;

/** Referencia de una recomendación: texto y, si existe, enlace verificado. */
final readonly class Fuente
{
    public function __construct(
        public string $texto,
        public ?string $url = null,
    ) {}

    public static function aasm(): self
    {
        return new self('Consenso AASM y Sleep Research Society: 7 horas o más por noche', 'https://aasm.org/seven-or-more-hours-of-sleep-per-night-a-health-necessity-for-adults');
    }

    public static function memoriaYSueno(): self
    {
        return new self('Metaanálisis: la privación de sueño perjudica la memoria (Hedges g de 0,62 antes de aprender y 0,28 después)', 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC8893218/');
    }

    public static function ericsson(): self
    {
        return new self('Ericsson, Krampe y Tesch-Römer (1993), con músicos de élite; no es una ley para cualquier tarea', 'https://notes.andymatuschak.org/zEkCRJXM9NYCXxzFoDaNhL');
    }

    public static function pink(): self
    {
        return new self('Pink, When (2018); revisión de Schmidt et al. 2007 sobre el rendimiento según la hora', 'https://pubmed.ncbi.nlm.nih.gov/18066734/');
    }

    public static function albulescu(): self
    {
        return new self('Albulescu et al. 2022: microdescansos con efecto pequeño en vigor y fatiga', 'https://journals.plos.org/plosone/article?id=10.1371%2Fjournal.pone.0272460');
    }

    public static function duhigg(): self
    {
        return new self('Duhigg, El poder de los hábitos (cifras del autor, sin verificación independiente)');
    }

    public static function aeon(): self
    {
        return new self('Aeon et al. 2021: la planificación se asocia con mejor rendimiento (correlación, no causalidad)', 'https://journals.plos.org/plosone/article?id=10.1371%2Fjournal.pone.0245066');
    }

    public static function dunlosky(): self
    {
        return new self('Dunlosky et al. 2013: la práctica de recuperación y la distribuida tienen utilidad alta', 'https://pubmed.ncbi.nlm.nih.gov/26173288/');
    }

    public static function gollwitzer(): self
    {
        return new self('Gollwitzer y Sheeran 2006: intenciones de implementación', 'https://psycnet.apa.org/record/2007-19538-002');
    }

    public static function reynerHorne(): self
    {
        return new self('Reyner y Horne 1997: muestra de 12 personas, en simulador de manejo', 'https://onlinelibrary.wiley.com/doi/abs/10.1111/j.1469-8986.1997.tb02148.x');
    }
}
