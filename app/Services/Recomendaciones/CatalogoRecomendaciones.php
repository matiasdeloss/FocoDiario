<?php

namespace App\Services\Recomendaciones;

use App\Enums\CategoriaRecomendacion;
use Illuminate\Support\Collection;

/**
 * Consejos de vida y de estudio que no dependen de tus datos. Cada uno lleva su fuente
 * o, si no hay una sólida, se marca como consejo general (fuente null).
 */
final class CatalogoRecomendaciones
{
    /** @return Collection<int, ConsejoGeneral> */
    public static function todos(): Collection
    {
        $E = CategoriaRecomendacion::Estudio;
        $S = CategoriaRecomendacion::Salud;
        $P = CategoriaRecomendacion::Productividad;
        $B = CategoriaRecomendacion::Bienestar;

        return collect([
            new ConsejoGeneral('pomodoro', $E, 'Estudiá con la técnica Pomodoro', 'Trabajá 25 minutos sin distracciones y descansá 5. Cada cuatro ciclos, una pausa larga de 15 a 30 minutos.', 'bi-hourglass-split', new Fuente('Técnica de Francesco Cirillo; la evidencia científica es limitada, pero ordena el trabajo en bloques'), 'estudio.index', 'Ir a Estudio'),
            new ConsejoGeneral('repaso-espaciado', $E, 'Repasá con intervalos crecientes', 'Repasar al día siguiente, a los 3 días, a la semana y al mes fija mejor lo aprendido que estudiar todo de una vez.', 'bi-arrow-repeat', Fuente::dunlosky(), 'recordatorios.index', 'Crear recordatorio'),
            new ConsejoGeneral('recuperacion', $E, 'Practicá recordando, no releyendo', 'Cerrá los apuntes y escribí todo lo que recuerdes; después corregí. Cuesta más y rinde más que subrayar o releer.', 'bi-lightbulb', Fuente::dunlosky(), 'estudio.index', 'Ir a Estudio'),
            new ConsejoGeneral('intercalar', $E, 'Intercalá temas al estudiar', 'Mezclar tipos de problemas en una misma sesión ayuda a distinguirlos y a elegir el método correcto en el examen.', 'bi-shuffle', new Fuente('Rohrer y Taylor 2007: mezclar problemas de matemática mejoró el rendimiento en la prueba final'), 'estudio.index', 'Ir a Estudio'),
            new ConsejoGeneral('dormir-examen', $E, 'Dormí 7 a 9 horas antes de un examen', 'Robarle horas a la noche para repasar suele salir caro: dormir después de estudiar consolida la memoria.', 'bi-moon-stars', Fuente::memoriaYSueno()),
            new ConsejoGeneral('explicar', $E, 'Explicá el tema con tus palabras', 'Si no lo podés explicar de forma simple, todavía hay huecos. Probá contárselo a alguien o escribilo como si enseñaras.', 'bi-chat-left-text', new Fuente('Chi et al. 1994: la autoexplicación mejora la comprensión (la técnica de Feynman es un consejo general)'), 'notas.index', 'Escribir una nota'),
            new ConsejoGeneral('multitarea', $E, 'Estudiá con una sola cosa a la vez', 'Dejá el celular en otra habitación durante el bloque. Cambiar de tarea seguido resta atención y memoria.', 'bi-phone-vibrate', new Fuente('Ophir, Nass y Wagner 2009 (PNAS): quienes hacen más multitarea mediática filtran peor las distracciones')),
            new ConsejoGeneral('foco-profundo', $P, 'Reservá un bloque de foco profundo', 'Elegí 90 minutos al día, sin avisos, para lo más difícil o importante. Protegelo como si fuera una cita.', 'bi-bullseye', new Fuente('Newport, Deep Work (2016); enfoque del autor, no un estudio controlado'), 'agenda.index', 'Ir a la Agenda'),
            new ConsejoGeneral('dos-minutos', $P, 'Regla de los 2 minutos', 'Si algo se resuelve en menos de 2 minutos, hacelo ya en lugar de anotarlo. Es más barato que gestionarlo después.', 'bi-stopwatch', new Fuente('Allen, Getting Things Done (2001); método del autor'), 'tareas.index', 'Ver tareas'),
            new ConsejoGeneral('planificar-semana', $P, 'Planificá tu semana el domingo', 'Diez minutos para ver parciales, entregas y compromisos, y repartirlos en la semana, evitan el amontonamiento del final.', 'bi-calendar-week', Fuente::aeon(), 'calendario.index', 'Ir al Calendario'),
            new ConsejoGeneral('preparar-dia', $P, 'Dejá listo el día siguiente', 'Antes de cerrar la jornada, anotá las 3 tareas de mañana y preparí lo que necesites. Arrancar es lo más difícil.', 'bi-journal-check', Fuente::gollwitzer(), 'tareas.index', 'Crear tarea'),
            new ConsejoGeneral('tarea-dificil', $P, 'Empezá por lo más difícil', 'Si tu energía es mayor por la mañana, aprovechala para la tarea que más te cuesta y dejá lo rutinario para después.', 'bi-brightness-high', Fuente::pink(), 'tareas.index', 'Ver tareas'),
            new ConsejoGeneral('pausas', $S, 'Hacé pausas cortas y tomá agua', 'Levantate cada hora, estirá y tomá agua. Los microdescansos tienen un efecto pequeño pero real en el cansancio.', 'bi-cup-straw', Fuente::albulescu()),
            new ConsejoGeneral('ejercicio-corto', $S, 'Sumá ejercicio corto', 'Una caminata rápida de 10 a 20 minutos cuenta. Los adultos deberían llegar a 150 minutos de actividad moderada por semana.', 'bi-bicycle', new Fuente('Organización Mundial de la Salud, Directrices sobre actividad física y hábitos sedentarios (2020)', 'https://www.who.int/publications/i/item/9789240015128'), 'registro.index', 'Ir al Registro'),
            new ConsejoGeneral('sueno-regular', $S, 'Mantené un horario de sueño regular', 'Acostate y levantate a horas parecidas, también los fines de semana, y dormí 7 horas o más.', 'bi-moon', Fuente::aasm()),
            new ConsejoGeneral('luz-natural', $S, 'Buscá luz natural por la mañana', 'Salir unos minutos temprano ayuda a ordenar el reloj interno del sueño.', 'bi-sun'),
            new ConsejoGeneral('redes', $B, 'Ponete un límite a las redes sociales', 'Unos 30 minutos al día en total, con horarios definidos, se asoció con menos soledad y síntomas depresivos.', 'bi-phone', new Fuente('Hunt et al. 2018, Journal of Social and Clinical Psychology: estudio con universitarios durante 3 semanas')),
            new ConsejoGeneral('gratitud', $B, 'Anotá tres cosas buenas del día', 'Escribir por la noche lo que salió bien entrena la atención hacia lo positivo y cierra la jornada con calma.', 'bi-journal-heart', new Fuente('Emmons y McCullough 2003: diarios de gratitud y bienestar (efectos modestos)'), 'notas.index', 'Escribir una nota'),
            new ConsejoGeneral('recompensa', $B, 'Premiá tus bloques de foco', 'Terminá un bloque y date un rato de ocio sin culpa. Descansar bien te deja mejor para el siguiente.', 'bi-cup-hot', Fuente::duhigg()),
            new ConsejoGeneral('respirar', $B, 'Respirá lento un minuto', 'Cuando notes tensión antes de un examen, exhalá más largo de lo que inhalás durante un minuto.', 'bi-wind'),
        ]);
    }

    /**
     * Los consejos (o los de una categoría) en un orden que cambia cada día pero no al recargar.
     * Sin filtro se reparte entre categorías para que ninguna domine la columna.
     *
     * @return Collection<int, ConsejoGeneral>
     */
    public static function delDia(string $fecha, ?CategoriaRecomendacion $categoria = null): Collection
    {
        $ordenados = self::todos()
            ->when($categoria, fn (Collection $c) => $c->filter(fn (ConsejoGeneral $x) => $x->categoria === $categoria))
            ->sortBy(fn (ConsejoGeneral $x) => crc32($fecha.'|'.$x->clave))
            ->values();

        if ($categoria) {
            return $ordenados;
        }

        $grupos = $ordenados->groupBy(fn (ConsejoGeneral $x) => $x->categoria->value);
        $salida = collect();
        for ($i = 0, $max = $grupos->map->count()->max() ?? 0; $i < $max; $i++) {
            foreach ($grupos as $grupo) {
                if ($grupo->has($i)) {
                    $salida->push($grupo[$i]);
                }
            }
        }

        return $salida;
    }
}
