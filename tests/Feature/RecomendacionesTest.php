<?php

namespace Tests\Feature;

use App\Enums\EstadoTarea;
use App\Enums\PrioridadTarea;
use App\Enums\TipoCategoria;
use App\Enums\TipoRecomendacion;
use App\Models\BloqueTiempo;
use App\Models\Categoria;
use App\Models\Recordatorio;
use App\Models\Tarea;
use App\Services\Recomendaciones\MotorRecomendaciones;
use App\Services\Recomendaciones\Recomendacion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RecomendacionesTest extends TestCase
{
    use RefreshDatabase;

    /** Lunes 28/09/2026 a la hora indicada. */
    private function a(string $hora, string $dia = '2026-09-28'): CarbonImmutable
    {
        return CarbonImmutable::parse("$dia $hora");
    }

    /** @return Collection<int, Recomendacion> */
    private function generar(CarbonImmutable $ahora): Collection
    {
        return app(MotorRecomendaciones::class)->generar($ahora);
    }

    private function buscar(Collection $recomendaciones, string $titulo): ?Recomendacion
    {
        return $recomendaciones->first(fn (Recomendacion $r) => $r->titulo === $titulo);
    }

    private function bloque(TipoCategoria $tipo, string $inicio, string $fin, string $nombre = 'Categoría'): BloqueTiempo
    {
        $categoria = Categoria::firstOrCreate(['nombre' => $nombre.$tipo->value], ['tipo' => $tipo]);

        return BloqueTiempo::create(['categoria_id' => $categoria->id, 'inicio' => $inicio, 'fin' => $fin]);
    }

    public function test_sueno_sin_datos_usa_las_7_de_la_manana(): void
    {
        $sueno = $this->buscar($this->generar($this->a('12:00')), 'Tu horario de sueño sugerido');

        $this->assertSame('23:00', $sueno->datos['Hora sugerida para acostarte']);
        $this->assertSame('07:00', $sueno->datos['Hora sugerida para despertar']);
        $this->assertNotNull($sueno->fuente);
        $this->assertStringContainsString('aasm.org', $sueno->fuente->url);
    }

    public function test_sueno_con_datos_usa_la_mediana_del_primer_bloque_de_los_ultimos_7_dias(): void
    {
        // Primeros bloques: 08:00, 09:00 y 10:00 -> mediana 09:00. Los bloques posteriores del día no cuentan.
        $this->bloque(TipoCategoria::Productiva, '2026-09-25 08:00', '2026-09-25 09:00');
        $this->bloque(TipoCategoria::Productiva, '2026-09-25 15:00', '2026-09-25 16:00');
        $this->bloque(TipoCategoria::Productiva, '2026-09-26 09:00', '2026-09-26 10:00');
        $this->bloque(TipoCategoria::Productiva, '2026-09-27 10:00', '2026-09-27 11:00');
        // Fuera de la ventana de 7 días: no cuenta.
        $this->bloque(TipoCategoria::Productiva, '2026-09-10 05:00', '2026-09-10 06:00');

        $sueno = $this->buscar($this->generar($this->a('12:00')), 'Tu horario de sueño sugerido');

        $this->assertSame('09:00', $sueno->datos['Hora sugerida para despertar']);
        $this->assertSame('01:00', $sueno->datos['Hora sugerida para acostarte']);
    }

    public function test_cerca_de_la_hora_de_dormir_sugiere_cerrar_pantallas(): void
    {
        $noche = $this->generar($this->a('21:30'));
        $dia = $this->generar($this->a('15:00'));

        $aviso = $this->buscar($noche, 'Se acerca la hora de dormir');
        $this->assertNotNull($aviso);
        $this->assertStringContainsString('pantallas', $aviso->mensaje);
        $this->assertNull($this->buscar($dia, 'Se acerca la hora de dormir'));
    }

    public function test_foco_bajo_alienta_a_completar_3_o_4_horas(): void
    {
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 08:00', '2026-09-28 09:00');

        $r = $this->buscar($this->generar($this->a('11:00')), 'Apuntá a 3 o 4 horas de foco');

        $this->assertNotNull($r);
        $this->assertStringContainsString('1 h', $r->mensaje);
        $this->assertNotNull($r->fuente);
    }

    public function test_mas_de_4_horas_de_foco_recomienda_descansar(): void
    {
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 06:00', '2026-09-28 08:30');
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 09:30', '2026-09-28 11:30');

        $recomendaciones = $this->generar($this->a('14:00'));

        $this->assertNotNull($this->buscar($recomendaciones, 'Ya superaste las 4 horas de foco'));
        $this->assertNull($this->buscar($recomendaciones, 'Apuntá a 3 o 4 horas de foco'));
    }

    public function test_balance_pide_foco_si_hay_mas_ocio_que_trabajo(): void
    {
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 08:00', '2026-09-28 08:30');
        $this->bloque(TipoCategoria::Ocio, '2026-09-28 09:00', '2026-09-28 10:30');

        $recomendaciones = $this->generar($this->a('11:00'));

        $this->assertNotNull($this->buscar($recomendaciones, 'Hoy hay más ocio que foco'));

        BloqueTiempo::query()->delete();
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 08:00', '2026-09-28 10:00');
        $this->bloque(TipoCategoria::Ocio, '2026-09-28 10:00', '2026-09-28 10:30');

        $this->assertNull($this->buscar($this->generar($this->a('11:00')), 'Hoy hay más ocio que foco'));
    }

    public function test_sin_actividad_pasadas_las_10_invita_a_un_bloque_de_25_minutos(): void
    {
        $this->assertNull($this->buscar($this->generar($this->a('09:00')), 'Arrancá con un bloque corto'));

        $r = $this->buscar($this->generar($this->a('10:30')), 'Arrancá con un bloque corto');
        $this->assertNotNull($r);
        $this->assertStringContainsString('25 minutos', $r->mensaje);

        $this->bloque(TipoCategoria::Productiva, '2026-09-28 10:00', '2026-09-28 10:20');
        $this->assertNull($this->buscar($this->generar($this->a('10:30')), 'Arrancá con un bloque corto'));
    }

    public function test_tareas_vencidas_generan_una_alerta(): void
    {
        Tarea::factory()->create(['fecha_limite' => '2026-09-20', 'estado' => EstadoTarea::Pendiente]);
        Tarea::factory()->create(['fecha_limite' => '2026-09-01', 'estado' => EstadoTarea::Completada]);

        $r = $this->buscar($this->generar($this->a('11:00')), 'Tenés una tarea vencida');

        $this->assertNotNull($r);
        $this->assertSame(TipoRecomendacion::Alerta, $r->tipo);
    }

    public function test_sin_tarea_alta_sugiere_elegir_la_mas_importante_y_sin_tareas_sugiere_planificar(): void
    {
        $this->assertNotNull($this->buscar($this->generar($this->a('11:00')), 'No tenés tareas abiertas'));

        Tarea::factory()->create(['prioridad' => PrioridadTarea::Media, 'fecha_limite' => null]);
        $recomendaciones = $this->generar($this->a('11:00'));
        $this->assertNotNull($this->buscar($recomendaciones, 'Elegí la tarea más importante del día'));
        $this->assertNull($this->buscar($recomendaciones, 'No tenés tareas abiertas'));

        Tarea::factory()->create(['prioridad' => PrioridadTarea::Alta, 'fecha_limite' => '2026-09-28']);
        $this->assertNull($this->buscar($this->generar($this->a('11:00')), 'Elegí la tarea más importante del día'));
    }

    public function test_recordatorios_atrasados_y_proximos(): void
    {
        Recordatorio::create(['mensaje' => 'Pagar la luz', 'recordar_en' => '2026-09-28 08:00']);
        Recordatorio::create(['mensaje' => 'Llamar al dentista', 'recordar_en' => '2026-09-28 11:30']);
        Recordatorio::create(['mensaje' => 'Mañana', 'recordar_en' => '2026-09-29 11:30']);

        $recomendaciones = $this->generar($this->a('11:00'));

        $this->assertNotNull($this->buscar($recomendaciones, 'Recordatorios atrasados'));
        $proximo = $this->buscar($recomendaciones, 'Recordatorio en la próxima hora');
        $this->assertStringContainsString('Llamar al dentista', $proximo->mensaje);
        $this->assertStringNotContainsString('Mañana', $proximo->mensaje);
    }

    public function test_momento_del_dia_cambia_segun_la_hora(): void
    {
        $this->assertNotNull($this->buscar($this->generar($this->a('09:00')), 'Momento de pico'));
        $this->assertNotNull($this->buscar($this->generar($this->a('13:00')), 'Momento de valle'));
        $this->assertNotNull($this->buscar($this->generar($this->a('17:00')), 'Momento de recuperación'));
        $this->assertNull($this->buscar($this->generar($this->a('02:00')), 'Momento de pico'));
    }

    public function test_mas_de_90_minutos_seguidos_sugieren_un_descanso(): void
    {
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 09:00', '2026-09-28 10:00');
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 10:02', '2026-09-28 11:00');

        $r = $this->buscar($this->generar($this->a('11:05')), 'Hacé una pausa');

        $this->assertNotNull($r);
        $this->assertStringContainsString('2 h', $r->mensaje);
        $this->assertStringContainsString('plos.org', $r->fuente->url);

        // Una hora después de terminar ya no hay tramo en curso.
        $this->assertNull($this->buscar($this->generar($this->a('12:30')), 'Hacé una pausa'));
    }

    public function test_un_descanso_en_el_medio_corta_el_tramo(): void
    {
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 09:00', '2026-09-28 10:00');
        $this->bloque(TipoCategoria::Descanso, '2026-09-28 10:00', '2026-09-28 10:15');
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 10:15', '2026-09-28 11:00');

        $this->assertNull($this->buscar($this->generar($this->a('11:05')), 'Hacé una pausa'));
    }

    public function test_logro_por_3_horas_de_foco(): void
    {
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 08:00', '2026-09-28 11:00');

        $r = $this->buscar($this->generar($this->a('12:00')), 'Llegaste a las 3 horas de foco');

        $this->assertNotNull($r);
        $this->assertSame(TipoRecomendacion::Logro, $r->tipo);
        $this->assertNull($this->buscar($this->generar($this->a('12:00', '2026-09-29')), 'Llegaste a las 3 horas de foco'));
    }

    public function test_logro_por_tarea_completada_hoy(): void
    {
        Tarea::factory()->create(['estado' => EstadoTarea::Completada]);
        Tarea::query()->update(['updated_at' => '2026-09-28 10:00:00']);

        $this->assertNotNull($this->buscar($this->generar($this->a('12:00')), 'Completaste una tarea hoy'));
        $this->assertNull($this->buscar($this->generar($this->a('12:00', '2026-09-29')), 'Completaste una tarea hoy'));
    }

    public function test_reglas_extra_planificar_manana_revision_semanal_y_ejercicio(): void
    {
        $this->assertNotNull($this->buscar($this->generar($this->a('21:00')), 'Planificá mañana'));
        $this->assertNull($this->buscar($this->generar($this->a('12:00')), 'Planificá mañana'));

        $this->assertNotNull($this->buscar($this->generar($this->a('12:00', '2026-09-27')), 'Revisión semanal'));
        $this->assertNull($this->buscar($this->generar($this->a('12:00')), 'Revisión semanal'));

        $this->assertNotNull($this->buscar($this->generar($this->a('12:00')), 'Movete un rato'));
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 07:00', '2026-09-28 07:30', 'Ejercicio ');
        $this->assertNull($this->buscar($this->generar($this->a('12:00')), 'Movete un rato'));
    }

    public function test_ocio_como_recompensa_y_repaso_tras_un_bloque_de_foco(): void
    {
        $this->bloque(TipoCategoria::Productiva, '2026-09-28 09:00', '2026-09-28 09:50');

        $recomendaciones = $this->generar($this->a('10:00'));

        $this->assertNotNull($this->buscar($recomendaciones, 'Te ganaste un rato de ocio'));
        $this->assertNotNull($this->buscar($recomendaciones, 'Programá el repaso de lo que estudiaste'));
    }

    public function test_el_motor_ordena_por_prioridad_de_mayor_a_menor(): void
    {
        Tarea::factory()->create(['fecha_limite' => '2026-09-20']);

        $prioridades = $this->generar($this->a('11:00'))->pluck('prioridad')->all();

        $ordenadas = $prioridades;
        rsort($ordenadas);
        $this->assertSame($ordenadas, $prioridades);
        $this->assertSame(90, $prioridades[0]);
    }

    public function test_las_pantallas_muestran_las_recomendaciones(): void
    {
        $this->get(route('hoy'))->assertOk()->assertSee('Recomendaciones para ahora');

        $this->get(route('recomendaciones.index'))
            ->assertOk()
            ->assertSee('Hora sugerida para acostarte')
            ->assertSee('aasm.org', false);
    }
}
