<?php

namespace Database\Seeders;

use App\Enums\ColorActividad;
use App\Enums\EstadoSesion;
use App\Enums\EstadoTarea;
use App\Enums\EstiloEstudio;
use App\Enums\OrigenBloque;
use App\Enums\PrioridadTarea;
use App\Enums\TipoCaja;
use App\Enums\TipoContexto;
use App\Enums\TipoIntervalo;
use App\Enums\ZonaSemana;
use App\Models\BloqueTiempo;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\ColumnaTablero;
use App\Models\Contexto;
use App\Models\IntervaloEstudio;
use App\Models\Nota;
use App\Models\Recordatorio;
use App\Models\SesionEstudio;
use App\Models\Tarea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos de ejemplo de un estudiante de tecnología (carrera de sistemas): materias con color, tareas en el tablero,
 * recordatorios, notas, planner semanal y hojas del día, estudio con pomodoros y eventos para el calendario.
 * Todo se calcula respecto de hoy, así que siempre hay pasado, semana actual y próximas semanas.
 * Pensado para una base recién migrada (php artisan migrate:fresh --seed).
 */
class EstudianteTecnologiaSeeder extends Seeder
{
    /** @var array<string, Contexto> */
    private array $m = [];

    private Carbon $hoy;

    private Carbon $lunes;

    public function run(): void
    {
        $this->hoy = Carbon::today();
        $this->lunes = $this->hoy->copy()->startOfWeek();

        $this->contextos();
        $this->tablero();
        $this->recordatorios();
        $this->notas();
        $this->estudio();
        $this->agenda();
    }

    /* ---------- Materias, temas y actividades ---------- */

    private function contextos(): void
    {
        $carrera = Contexto::where('nombre', 'Carrera')->whereNull('contexto_padre_id')->first();
        $vida = Contexto::where('nombre', 'Vida cotidiana')->whereNull('contexto_padre_id')->first();
        $personales = Contexto::where('nombre', 'Proyectos personales')->whereNull('contexto_padre_id')->first();

        $materias = [
            'Programación II' => [ColorActividad::Terracota, ['POO y patrones', 'Estructuras de datos', 'Testing']],
            'Bases de Datos' => [ColorActividad::AzulPolvo, ['Normalización', 'SQL avanzado', 'Transacciones']],
            'Redes de Computadoras' => [ColorActividad::Celeste, ['Modelo OSI', 'Direccionamiento IP', 'Enrutamiento']],
            'Sistemas Operativos' => [ColorActividad::Ocre, ['Planificación de procesos', 'Memoria virtual', 'Sincronización']],
            'Ingeniería de Software' => [ColorActividad::Lavanda, ['Scrum', 'UML', 'Requerimientos']],
            'Matemática Discreta' => [ColorActividad::Ciruela, ['Grafos', 'Lógica proposicional', 'Combinatoria']],
            'Inglés Técnico' => [ColorActividad::Salvia, ['Lectura de documentación', 'Vocabulario de IT']],
        ];

        foreach ($materias as $nombre => [$color, $temas]) {
            $materia = Contexto::create([
                'nombre' => $nombre,
                'tipo' => TipoContexto::Materia,
                'contexto_padre_id' => $carrera->id,
                'color' => $color->value,
            ]);
            $this->m[$nombre] = $materia;

            foreach ($temas as $tema) {
                $this->m[$tema] = Contexto::create(['nombre' => $tema, 'tipo' => TipoContexto::Tema, 'contexto_padre_id' => $materia->id]);
            }
        }

        // Otras actividades con color para la agenda.
        $this->m['Gimnasio'] = Contexto::create(['nombre' => 'Gimnasio', 'tipo' => TipoContexto::Tema, 'contexto_padre_id' => $vida->id, 'color' => ColorActividad::Oliva->value]);
        $this->m['Vóley'] = Contexto::create(['nombre' => 'Vóley', 'tipo' => TipoContexto::Tema, 'contexto_padre_id' => $vida->id, 'color' => ColorActividad::Rosa->value]);
        $this->m['Proyecto personal'] = Contexto::create(['nombre' => 'Portfolio y side projects', 'tipo' => TipoContexto::Proyecto, 'contexto_padre_id' => $personales->id, 'color' => ColorActividad::Arena->value]);
        $this->m['Homelab'] = Contexto::create(['nombre' => 'Homelab', 'tipo' => TipoContexto::Proyecto, 'contexto_padre_id' => $personales->id]);
    }

    /* ---------- Tablero de tareas ---------- */

    private function tablero(): void
    {
        $col = [
            'pendiente' => ColumnaTablero::where('categoria', EstadoTarea::Pendiente)->orderBy('posicion')->first(),
            'progreso' => ColumnaTablero::where('categoria', EstadoTarea::EnProgreso)->orderBy('posicion')->first(),
            'hecha' => ColumnaTablero::where('categoria', EstadoTarea::Completada)->orderBy('posicion')->first(),
        ];
        // Columna personalizada, como puede crearla el usuario desde el tablero.
        $col['revision'] = ColumnaTablero::create([
            'nombre' => 'En revisión',
            'categoria' => EstadoTarea::EnProgreso,
            'posicion' => $col['progreso']->posicion + 1,
        ]);
        ColumnaTablero::where('id', $col['hecha']->id)->update(['posicion' => $col['revision']->posicion + 1]);

        $d = fn (int $dias) => $this->hoy->copy()->addDays($dias);
        $A = PrioridadTarea::Alta;
        $M = PrioridadTarea::Media;
        $B = PrioridadTarea::Baja;

        // [título, descripción, contexto, fecha límite, prioridad, columna, días desde hoy en que se completó]
        $tareas = [
            ['TP 3: árbol binario de búsqueda', 'Insertar, buscar y recorrer en orden. Entregar con pruebas unitarias.', 'Programación II', $d(2), $A, 'progreso'],
            ['Estudiar patrones Factory y Observer', 'Leer el capítulo 5 del libro y hacer el ejemplo en Java.', 'Programación II', $d(6), $M, 'pendiente'],
            ['Refactor del TP 2 con interfaces', null, 'Programación II', $d(-3), $M, 'hecha', -3],
            ['Práctica de normalización hasta 3FN', 'Guía 4, ejercicios 1 al 8.', 'Bases de Datos', $d(1), $A, 'pendiente'],
            ['Consultas con JOIN y subconsultas', 'Resolver la guía en el motor local y comparar planes con EXPLAIN.', 'Bases de Datos', $d(4), $M, 'progreso'],
            ['Modelo entidad-relación del TP integrador', 'Diagrama del sistema de biblioteca.', 'Bases de Datos', $d(9), $A, 'revision'],
            ['Instalar el dump de la práctica', null, 'Bases de Datos', $d(-5), $B, 'hecha', -5],
            ['Laboratorio de subnetting', 'Dividir 192.168.10.0/24 en 6 subredes con VLSM.', 'Redes de Computadoras', $d(3), $A, 'pendiente'],
            ['Armar la topología en Packet Tracer', 'Tres routers, dos switches y RIP.', 'Redes de Computadoras', $d(7), $M, 'pendiente'],
            ['Resumen del modelo OSI y TCP/IP', null, 'Redes de Computadoras', $d(-2), $B, 'hecha', -2],
            ['Simular planificación Round Robin', 'Quantum de 4 ms con 5 procesos. Diagrama de Gantt.', 'Sistemas Operativos', $d(5), $M, 'pendiente'],
            ['Ejercicios de semáforos y productor-consumidor', null, 'Sistemas Operativos', $d(10), $M, 'pendiente'],
            ['Script en bash para monitorear procesos', 'Listar los 5 procesos con más CPU cada 10 s.', 'Sistemas Operativos', $d(-1), $B, 'hecha', -1],
            ['Historias de usuario del proyecto grupal', 'Definir backlog inicial y criterios de aceptación.', 'Ingeniería de Software', $d(2), $A, 'revision'],
            ['Diagrama de clases y de secuencia', null, 'Ingeniería de Software', $d(8), $M, 'pendiente'],
            ['Retrospectiva del primer sprint', null, 'Ingeniería de Software', $d(-6), $B, 'hecha', -6],
            ['Guía de grafos: caminos y árboles', 'Ejercicios 3, 5 y 9.', 'Matemática Discreta', $d(4), $A, 'pendiente'],
            ['Repasar tablas de verdad', null, 'Matemática Discreta', $d(-4), $B, 'hecha', -4],
            ['Leer la documentación de Docker en inglés', 'Para el glosario de la materia.', 'Inglés Técnico', $d(6), $B, 'pendiente'],
            ['Presentación oral: mi stack favorito', '5 minutos, en inglés.', 'Inglés Técnico', $d(12), $M, 'pendiente'],
            ['Terminar el portfolio con Astro', 'Sección de proyectos y contacto.', 'Portfolio y side projects', $d(15), $M, 'progreso'],
            ['Subir el bot de Discord a un VPS', null, 'Portfolio y side projects', null, $B, 'pendiente'],
            ['Configurar Proxmox y una VM con Ubuntu Server', null, 'Homelab', null, $B, 'pendiente'],
            ['Entregar el TP de Programación I atrasado', null, 'Programación II', $d(-2), $A, 'pendiente'],
            ['Matemática Discreta: inscribirse al final', 'Cierra la inscripción esta semana.', 'Matemática Discreta', $d(1), $M, 'pendiente'],
            ['Hacer backup del repositorio de la facultad', null, 'Homelab', $d(0), $B, 'hecha', 0],
        ];

        foreach ($tareas as $t) {
            $tarea = Tarea::create([
                'titulo' => $t[0],
                'descripcion' => $t[1],
                'contexto_id' => $this->m[$t[2] === 'Portfolio y side projects' ? 'Proyecto personal' : $t[2]]->id,
                'fecha_limite' => $t[3],
                'prioridad' => $t[4],
                'columna_id' => $col[$t[5]]->id,
                'estado' => $col[$t[5]]->categoria,
            ]);

            if (isset($t[6])) {
                $fecha = $this->hoy->copy()->addDays($t[6])->setTime(rand(9, 21), rand(0, 59));
                if ($fecha->isFuture()) {
                    $fecha = now()->subMinutes(20);
                }
                DB::table('tareas')->where('id', $tarea->id)->update(['updated_at' => $fecha, 'created_at' => $fecha->copy()->subDays(4)]);
            }
        }
    }

    /* ---------- Recordatorios ---------- */

    private function recordatorios(): void
    {
        $en = fn (int $dias, string $hora) => $this->hoy->copy()->addDays($dias)->setTimeFromTimeString($hora);
        $tarea = fn (string $titulo) => Tarea::where('titulo', $titulo)->value('id');

        $lista = [
            ['Parcial de Bases de Datos', 'Unidades 1 a 4. Llevar calculadora y el lápiz negro.', $en(6, '09:00'), null, null],
            ['Entregar el TP 3 de Programación II', 'Subir el repositorio al aula virtual antes de las 23:59.', $en(2, '20:00'), null, $tarea('TP 3: árbol binario de búsqueda')],
            ['Reunión de grupo del proyecto de Software', 'Por Meet. Llevar las historias de usuario.', $en(1, '19:30'), null, $tarea('Historias de usuario del proyecto grupal')],
            ['Práctica de vóley', null, $en(0, '21:00'), null, null],
            ['Pagar internet y luz', null, $en(3, '10:00'), null, null],
            ['Turno con el médico', 'Control anual. Llevar el carnet.', $en(9, '11:15'), null, null],
            ['Cierre de inscripción a finales', 'En el sistema de alumnos, hasta las 18:00.', $en(1, '17:00'), null, $tarea('Matemática Discreta: inscribirse al final')],
            ['Parcial de Redes', 'Subnetting, VLSM y capa de transporte.', $en(13, '18:00'), null, null],
            ['Cumpleaños de Sofi', 'Comprar el regalo.', $en(5, '20:30'), null, null],
            ['Renovar el dominio del portfolio', null, $en(20, '09:00'), null, null],
            ['Devolver el libro de Sistemas Operativos', 'Biblioteca de la facultad.', $en(-2, '16:00'), $en(-2, '16:00'), null],
            ['Comprar un pendrive de 64 GB', null, $en(-4, '12:00'), $en(-4, '12:05'), null],
            ['Sacar entrada para el meetup de Laravel', 'Se agotan rápido.', null, null, null],
            ['Preguntar por la beca de conectividad', null, null, null, null],
            ['Actualizar el CV con los proyectos nuevos', null, null, null, null],
        ];

        foreach ($lista as [$mensaje, $descripcion, $cuando, $avisado, $tareaId]) {
            Recordatorio::create([
                'mensaje' => $mensaje,
                'descripcion' => $descripcion,
                'recordar_en' => $cuando,
                'avisado_en' => $avisado,
                'tarea_id' => $tareaId,
            ]);
        }
    }

    /* ---------- Notas ---------- */

    private function notas(): void
    {
        $f = fn (int $dias) => $this->hoy->copy()->addDays($dias);

        $lista = [
            ['Formas normales, resumen', "1FN: valores atómicos.\n2FN: sin dependencias parciales de la clave.\n3FN: sin dependencias transitivas.\nBCNF: todo determinante es clave candidata.", 'Normalización', $f(-1), true, ColorActividad::Rosa],
            ['Comandos de Git que siempre olvido', "git stash -u\ngit rebase -i HEAD~3\ngit reflog para recuperar commits\ngit bisect start", 'Ingeniería de Software', null, true, ColorActividad::Salvia],
            ['Subnetting rápido', "Hosts = 2^n - 2.\n/24 = 254 hosts, /26 = 62 hosts, /28 = 14 hosts.\nSalto = 256 - octeto de la máscara.", 'Direccionamiento IP', $f(0), false, ColorActividad::Arena],
            ['Ideas para el proyecto integrador', "- App de turnos para la biblioteca.\n- Panel de métricas con Laravel y gráficos.\n- Bot que avisa las fechas de entrega.", 'Ingeniería de Software', null, false, ColorActividad::Terracota],
            ['Clase de Sistemas Operativos', "Round Robin: cuanto pequeño = más cambios de contexto.\nSJF minimiza el tiempo de espera promedio pero puede dejar sin CPU a los procesos largos.", 'Planificación de procesos', $f(-3), false, ColorActividad::Oliva],
            ['Vocabulario de IT', "deploy = despliegue\nbackend = lado del servidor\nthroughput = rendimiento\nlatency = latencia\nbottleneck = cuello de botella", 'Vocabulario de IT', null, false, ColorActividad::Rosa],
            ['Para leer', "Clean Code (caps. 1 a 4).\nDesigning Data-Intensive Applications, cap. 1.\nDocumentación oficial de PostgreSQL sobre índices.", null, null, false, ColorActividad::Salvia],
            ['Recorridos en grafos', "BFS usa cola y encuentra el camino mínimo en grafos sin pesos.\nDFS usa pila o recursión y sirve para detectar ciclos.", 'Grafos', $f(2), false, ColorActividad::Arena],
            ['Checklist del examen de Redes', "- Modelo OSI capa por capa.\n- VLSM.\n- Diferencias TCP y UDP.\n- Tablas de enrutamiento estático.", 'Redes de Computadoras', $f(13), false, ColorActividad::Terracota],
        ];

        foreach ($lista as [$titulo, $contenido, $contexto, $fecha, $fijada, $color]) {
            Nota::create([
                'titulo' => $titulo,
                'contenido' => $contenido,
                'contexto_id' => $contexto !== null ? $this->m[$contexto]->id : null,
                'fecha' => $fecha,
                'fijada' => $fijada,
                'color' => $color,
            ]);
        }
    }

    /* ---------- Estudio: sesiones con pomodoros y bloques de tiempo ---------- */

    private function estudio(): void
    {
        $cat = Categoria::pluck('id', 'nombre');

        // [días atrás, hora de inicio, materia, tema, pomodoros, título de tarea relacionada o null]
        $sesiones = [
            [12, '18:00', 'Programación II', 'Estructuras de datos', 3, null],
            [11, '19:00', 'Matemática Discreta', 'Lógica proposicional', 2, 'Repasar tablas de verdad'],
            [10, '09:30', 'Bases de Datos', 'SQL avanzado', 4, null],
            [9, '18:30', 'Redes de Computadoras', 'Modelo OSI', 3, 'Resumen del modelo OSI y TCP/IP'],
            [8, '20:00', 'Sistemas Operativos', 'Sincronización', 2, null],
            [7, '10:00', 'Ingeniería de Software', 'Scrum', 3, 'Retrospectiva del primer sprint'],
            [6, '17:30', 'Programación II', 'POO y patrones', 4, 'Refactor del TP 2 con interfaces'],
            [5, '19:30', 'Bases de Datos', 'Normalización', 2, 'Instalar el dump de la práctica'],
            [4, '18:00', 'Matemática Discreta', 'Grafos', 3, null],
            [3, '20:00', 'Redes de Computadoras', 'Direccionamiento IP', 3, null],
            [2, '18:30', 'Sistemas Operativos', 'Planificación de procesos', 4, null],
            [1, '19:00', 'Programación II', 'Estructuras de datos', 3, 'TP 3: árbol binario de búsqueda'],
            [1, '09:00', 'Inglés Técnico', 'Lectura de documentación', 2, null],
        ];

        foreach ($sesiones as [$atras, $hora, $materia, $tema, $pomodoros, $tarea]) {
            $this->sesion($this->hoy->copy()->subDays($atras)->setTimeFromTimeString($hora), $materia, $tema, $pomodoros, $tarea, $cat['Estudio']);
        }

        // Hoy: pomodoros ya hechos, de modo que la tarjeta Pomodoro no arranque en cero.
        $inicioHoy = now()->copy()->subHours(3)->startOfHour();
        if ($inicioHoy->isSameDay($this->hoy)) {
            $this->sesion($inicioHoy, 'Bases de Datos', 'Normalización', 2, 'Práctica de normalización hasta 3FN', $cat['Estudio']);
        }

        // Bloques manuales de otras categorías para el registro de la semana.
        $manuales = [
            [10, '07:30', 60, 'Ejercicio'], [8, '07:30', 60, 'Ejercicio'], [6, '07:30', 60, 'Ejercicio'], [3, '07:30', 60, 'Ejercicio'],
            [1, '07:30', 60, 'Ejercicio'], [9, '22:00', 90, 'Entretenimiento'], [5, '21:30', 60, 'Entretenimiento'],
            [2, '21:30', 45, 'Redes sociales'], [7, '13:00', 45, 'Comidas'], [4, '13:30', 40, 'Comidas'], [1, '14:00', 30, 'Descanso'],
        ];
        foreach ($manuales as [$atras, $hora, $minutos, $categoria]) {
            $inicio = $this->hoy->copy()->subDays($atras)->setTimeFromTimeString($hora);
            BloqueTiempo::create([
                'categoria_id' => $cat[$categoria],
                'tarea_id' => null,
                'inicio' => $inicio,
                'fin' => $inicio->copy()->addMinutes($minutos),
                'origen' => OrigenBloque::Manual,
                'concentracion' => $categoria === 'Ejercicio' ? null : rand(2, 4),
            ]);
        }
    }

    private function sesion(Carbon $inicio, string $materia, string $tema, int $pomodoros, ?string $tituloTarea, int $categoriaEstudio): void
    {
        $tareaId = $tituloTarea !== null ? Tarea::where('titulo', $tituloTarea)->value('id') : null;
        $cursor = $inicio->copy();

        $sesion = SesionEstudio::create([
            'contexto_id' => $this->m[$materia]->id,
            'tarea_id' => $tareaId,
            'tema' => $tema,
            'estilo' => EstiloEstudio::Clasico,
            'foco_seg' => 1500,
            'descanso_seg' => 300,
            'descanso_largo_seg' => 900,
            'pomodoros_antes_largo' => 4,
            'estado' => EstadoSesion::Finalizada,
            'iniciada_en' => $inicio,
            'finalizada_en' => $inicio->copy()->addMinutes($pomodoros * 30),
        ]);

        for ($i = 1; $i <= $pomodoros; $i++) {
            $fin = $cursor->copy()->addMinutes(25);
            $bloque = BloqueTiempo::create([
                'categoria_id' => $categoriaEstudio,
                'tarea_id' => $tareaId,
                'inicio' => $cursor,
                'fin' => $fin,
                'origen' => OrigenBloque::Pomodoro,
                'concentracion' => rand(3, 5),
            ]);
            IntervaloEstudio::create([
                'sesion_id' => $sesion->id,
                'tipo' => TipoIntervalo::Foco,
                'clave' => (string) Str::uuid(),
                'inicio' => $cursor,
                'fin' => $fin,
                'planificado_seg' => 1500,
                'pausado_seg' => 0,
                'duracion_seg' => 1500,
                'completado' => true,
                'bloque_tiempo_id' => $bloque->id,
            ]);

            if ($i < $pomodoros) {
                IntervaloEstudio::create([
                    'sesion_id' => $sesion->id,
                    'tipo' => TipoIntervalo::Descanso,
                    'clave' => (string) Str::uuid(),
                    'inicio' => $fin,
                    'fin' => $fin->copy()->addMinutes(5),
                    'planificado_seg' => 300,
                    'pausado_seg' => 0,
                    'duracion_seg' => 300,
                    'completado' => true,
                    'bloque_tiempo_id' => null,
                ]);
            }

            $cursor = $fin->copy()->addMinutes(5);
        }
    }

    /* ---------- Agenda: planner semanal y hojas del día ---------- */

    private function agenda(): void
    {
        // Horario fijo de cursada y rutina: [día (0 = lunes), materia, título, inicio, fin]
        $horario = [
            [0, 'Programación II', 'Programación II · Teoría', '18:00', '20:00'],
            [0, 'Gimnasio', 'Gimnasio', '07:30', '08:30'],
            [1, 'Bases de Datos', 'Bases de Datos', '17:00', '19:00'],
            [1, 'Redes de Computadoras', 'Redes de Computadoras', '19:00', '21:00'],
            [2, 'Sistemas Operativos', 'Sistemas Operativos', '18:00', '20:30'],
            [2, 'Gimnasio', 'Gimnasio', '07:30', '08:30'],
            [3, 'Ingeniería de Software', 'Ingeniería de Software', '18:00', '20:00'],
            [3, 'Vóley', 'Vóley', '21:00', '22:30'],
            [4, 'Inglés Técnico', 'Inglés Técnico', '09:00', '11:00'],
            [4, 'Gimnasio', 'Gimnasio', '07:30', '08:30'],
            [4, 'Matemática Discreta', 'Matemática Discreta · Práctica', '16:00', '18:00'],
            [5, 'Proyecto personal', 'Portfolio y side projects', '10:00', '13:00'],
            [5, 'Homelab', 'Homelab: Proxmox', '16:00', '18:00'],
            [6, 'Matemática Discreta', 'Repaso de la semana', '17:00', '19:00'],
        ];

        foreach ([-1, 0, 1, 2] as $offset) {
            $lunes = $this->lunes->copy()->addWeeks($offset);

            foreach ($horario as [$dia, $actividad, $titulo, $desde, $hasta]) {
                Caja::create([
                    'fecha' => $lunes->copy()->addDays($dia),
                    'contexto_id' => $this->m[$actividad]->id,
                    'titulo' => $titulo,
                    'tipo' => TipoCaja::Texto,
                    'contenido' => null,
                    'hora_inicio' => $desde,
                    'hora_fin' => $hasta,
                    'hecha' => $offset < 0 || ($offset === 0 && $lunes->copy()->addDays($dia)->lt($this->hoy)),
                ]);
            }
        }

        // Esta semana: cajas de trabajo con lista de pasos (estas también se ven en la hoja del día).
        $este = fn (int $dia) => $this->lunes->copy()->addDays($dia);
        Caja::create(['fecha' => $este(1), 'contexto_id' => $this->m['Programación II']->id, 'titulo' => 'TP 3: árbol binario', 'tipo' => TipoCaja::Lista,
            'items' => [['texto' => 'Clase Nodo y Árbol', 'hecho' => true], ['texto' => 'Insertar y buscar', 'hecho' => true], ['texto' => 'Recorridos en orden y por niveles', 'hecho' => false], ['texto' => 'Pruebas unitarias', 'hecho' => false], ['texto' => 'Subir al repositorio', 'hecho' => false]],
            'hora_inicio' => '20:30', 'hora_fin' => '22:00']);
        Caja::create(['fecha' => $este(2), 'contexto_id' => $this->m['Bases de Datos']->id, 'titulo' => 'Práctica de 3FN', 'tipo' => TipoCaja::Lista,
            'items' => [['texto' => 'Ejercicios 1 al 4', 'hecho' => false], ['texto' => 'Ejercicios 5 al 8', 'hecho' => false]],
            'hora_inicio' => '16:00', 'hora_fin' => '17:30']);

        // Notas y Pendiente de cada semana.
        foreach ([-1, 0, 1] as $offset) {
            $lunes = $this->lunes->copy()->addWeeks($offset);
            Caja::create(['semana' => $lunes, 'zona' => ZonaSemana::Notas, 'tipo' => TipoCaja::Texto, 'titulo' => 'Notas',
                'contenido' => $offset === 0
                    ? "Parcial de Bases de Datos el ".$this->hoy->copy()->addDays(6)->translatedFormat('j \d\e F').".\nPedir los apuntes de Redes a Lucas.\nSprint review del proyecto el jueves."
                    : ($offset < 0 ? "Semana de entrega del TP 2.\nRepaso de listas y pilas." : "Empieza la unidad de Enrutamiento.\nReservar sala de estudio.")]);
            Caja::create(['semana' => $lunes, 'zona' => ZonaSemana::Pendiente, 'tipo' => TipoCaja::Lista, 'titulo' => 'Pendiente',
                'items' => $offset === 0
                    ? [['texto' => 'Terminar el TP 3 de Programación II', 'hecho' => false], ['texto' => 'Guía de normalización', 'hecho' => false], ['texto' => 'Laboratorio de subnetting', 'hecho' => false], ['texto' => 'Inscribirme al final de Matemática', 'hecho' => false], ['texto' => 'Subir el resumen de OSI al drive', 'hecho' => true]]
                    : [['texto' => 'Repasar la teoría de la semana', 'hecho' => $offset < 0], ['texto' => 'Ordenar los apuntes', 'hecho' => $offset < 0]]]);
        }

        $this->hojaDelDia();
    }

    /** Hoja del día de hoy y de mañana con cajas libres en la grilla de 12 columnas. */
    private function hojaDelDia(): void
    {
        $hoy = $this->hoy->copy();

        // Cajas libres de hoy (sin hora, con posición propia).
        Caja::create(['fecha' => $hoy, 'contexto_id' => $this->m['Bases de Datos']->id, 'titulo' => 'Bases de Datos', 'tipo' => TipoCaja::Lista,
            'items' => [['texto' => 'Repasar 1FN, 2FN y 3FN', 'hecho' => true], ['texto' => 'Guía 4, ejercicios 1 al 8', 'hecho' => false], ['texto' => 'Dudas para la consulta del viernes', 'hecho' => false]],
            'x' => 0, 'y' => 0, 'ancho' => 6, 'alto' => 9]);
        Caja::create(['fecha' => $hoy, 'contexto_id' => $this->m['Redes de Computadoras']->id, 'titulo' => 'Redes', 'tipo' => TipoCaja::Lista,
            'items' => [['texto' => 'Laboratorio de subnetting', 'hecho' => false], ['texto' => 'Leer capítulo de enrutamiento', 'hecho' => false]],
            'x' => 6, 'y' => 0, 'ancho' => 6, 'alto' => 9]);
        Caja::create(['fecha' => $hoy, 'contexto_id' => $this->m['Programación II']->id, 'titulo' => 'Programación II', 'tipo' => TipoCaja::Texto,
            'contenido' => "TP 3: falta el recorrido por niveles.\nUsar una cola para BFS.\nPreguntar en el foro por el formato de entrega.",
            'x' => 0, 'y' => 9, 'ancho' => 8, 'alto' => 8]);
        Caja::create(['fecha' => $hoy, 'titulo' => 'Orden de prioridades', 'tipo' => TipoCaja::Lista,
            'items' => [['texto' => '1. Entrega del TP 3', 'hecho' => false], ['texto' => '2. Práctica de normalización', 'hecho' => false], ['texto' => '3. Subnetting', 'hecho' => false]],
            'x' => 8, 'y' => 9, 'ancho' => 4, 'alto' => 8]);

        // Mañana.
        $manana = $hoy->copy()->addDay();
        Caja::create(['fecha' => $manana, 'contexto_id' => $this->m['Ingeniería de Software']->id, 'titulo' => 'Proyecto grupal', 'tipo' => TipoCaja::Lista,
            'items' => [['texto' => 'Definir el backlog', 'hecho' => false], ['texto' => 'Asignar historias por persona', 'hecho' => false], ['texto' => 'Subir el tablero a Trello', 'hecho' => false]],
            'x' => 0, 'y' => 0, 'ancho' => 6, 'alto' => 9]);
        Caja::create(['fecha' => $manana, 'contexto_id' => $this->m['Matemática Discreta']->id, 'titulo' => 'Matemática Discreta', 'tipo' => TipoCaja::Texto,
            'contenido' => "Guía de grafos: ejercicios 3, 5 y 9.\nRepasar caminos eulerianos y hamiltonianos.",
            'x' => 6, 'y' => 0, 'ancho' => 6, 'alto' => 9]);
    }
}
