# 2. Requisitos y funcionalidades

## Fase 1: MVP
| Módulo | Descripción |
|---|---|
| Tareas | Crear, editar, completar y borrar tareas. Campos: título, proyecto/materia, fecha límite, prioridad, estado. |
| Recordatorios | Aviso asociado a una fecha/hora, opcionalmente ligado a una tarea. |
| Pomodoro | Temporizador configurable (por defecto 25 min foco / 5 descanso / pausa larga cada 4). Asociable a una tarea. |
| Registro del día | Carga manual de bloques de tiempo con categoría (estudio, proyecto, ocio, descanso, otros), hora de inicio y fin. Los Pomodoros completados se registran solos. |
| Día en curso | Hora de despertar y de dormir para calcular horas despierto. |

## Fase 2: medición y equilibrio
- Estadísticas diarias y semanales: horas despierto, horas aprovechadas, horas de ocio, porcentaje aprovechado.
- Horarios de entretenimiento: ventanas y límites diarios por categoría de ocio, con aviso al excederse.
- Historial y comparación entre semanas.

## Fase 3: planificación y guía
- Planificador de horas por día (time blocking): distribuir el día en bloques antes de vivirlo.
- Sección de buenas prácticas de estudio (contenido de `03-investigacion-estudio.md`).
- Tareas recurrentes y plantillas de día.
- Módulo de hábitos (basado en Duhigg): registro opcional de señal (hora, lugar, emoción, personas, acción previa) al anotar ocio no planificado, y reglas "Cuando [señal], haré [rutina] porque [recompensa]". Ver `03-investigacion-estudio.md`.
- Rachas y pequeñas victorias visibles (tareas y Pomodoros completados).
- Cronotipo: cálculo a partir del punto medio del sueño en días libres, y sugerencia de bloques según pico, valle y recuperación.
- Puntuación de concentración (1 a 5) por bloque de foco, para descubrir las franjas horarias de mayor rendimiento.
- Descansos guiados tras cada Pomodoro (movimiento, sin celular) y plantilla de recuperación.
- Práctica de recuperación activa: tarjetas de conceptos clave por materia (ligada a repaso espaciado).

## Ideas futuras
- Registro automático de actividad (alta complejidad y privacidad).
- Repaso espaciado con tarjetas.
- Exportar datos.

## Requisitos no funcionales
- Uso personal: datos guardados localmente.
- Registro rápido: pocos clics.
- Funciona sin conexión si es posible.
- Los datos no deben perderse (respaldo/exportación).

## Modelo de datos preliminar
- **Categoría**: nombre, tipo (productiva / ocio / descanso).
- **Tarea**: título, proyecto, fecha límite, prioridad, estado.
- **Recordatorio**: fecha/hora, mensaje, tarea (opcional).
- **Bloque de tiempo**: inicio, fin, categoría, tarea (opcional), origen (manual / Pomodoro).
- **Día**: fecha, hora de despertar, hora de dormir.
