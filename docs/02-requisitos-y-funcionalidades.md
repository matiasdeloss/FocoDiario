# 2. Requisitos y funcionalidades

## Fase 1: MVP
| Módulo | Descripción |
|---|---|
| Tareas | Crear, editar, completar y borrar tareas. Campos: título, proyecto/materia, fecha límite, prioridad, estado. |
| Recordatorios | Aviso asociado a una fecha/hora, opcionalmente ligado a una tarea. |
| Pomodoro | Temporizador configurable (por defecto 25 min foco / 5 descanso / pausa larga cada 4). Asociable a una tarea. Ver "Estado actual". |
| Registro del día | Carga manual de bloques de tiempo con categoría (estudio, proyecto, ocio, descanso, otros), hora de inicio y fin. Los Pomodoros completados se registran solos. |

## Fase 2: medición y equilibrio
- Estadísticas diarias y semanales: horas aprovechadas, horas de ocio, porcentaje aprovechado. (Las horas despierto ya no se registran: ver "Decisiones de producto".)
- Horarios de entretenimiento: ventanas y límites diarios por categoría de ocio, con aviso al excederse.
- Historial y comparación entre semanas.

## Fase 3: planificación y guía
- Planificador de horas por día (time blocking): distribuir el día en bloques antes de vivirlo.
- Sección de buenas prácticas de estudio (contenido de `03-investigacion-estudio.md`).
- Tareas recurrentes y plantillas de día.
- Módulo de hábitos (basado en Duhigg): registro opcional de señal (hora, lugar, emoción, personas, acción previa) al anotar ocio no planificado, y reglas "Cuando [señal], haré [rutina] porque [recompensa]". Ver `03-investigacion-estudio.md`.
- Rachas y pequeñas victorias visibles (tareas y Pomodoros completados).
- Cronotipo estimado: sin horas de sueño registradas, se aproximaría con la hora habitual de la primera y la última actividad del día. Es una idea a evaluar, menos fiable que el punto medio del sueño.
- Puntuación de concentración (1 a 5) por bloque de foco, para descubrir las franjas horarias de mayor rendimiento.
- Descansos guiados tras cada Pomodoro (movimiento, sin celular) y plantilla de recuperación.
- Práctica de recuperación activa: tarjetas de conceptos clave por materia (ligada a repaso espaciado).

## Estado actual (2026-10)
Construido y con pruebas automáticas (393 de PHPUnit y pruebas en Node de la lógica de JavaScript en `tests/js/`):
- **Hoy:** nota rápida que crea nota, tarea o recordatorio, semana en curso, recordatorios pendientes, tareas abiertas, pomodoro y recomendaciones destacadas.
- **Agenda:** planificador semanal con cajas por materia o proyecto, listas de verificación y disposición editable.
- **Calendario:** vistas de mes, semana, día y lista, con bandeja lateral para ubicar lo que no tiene fecha.
- **Tareas:** lista y tablero kanban con columnas personalizables, prioridad, fecha límite, color y comentario.
- **Recordatorios y Registro** completos, con HTMX.
- **Contextos y Notas:** jerarquía entorno / materia / tema, bandeja de entrada, notas con título, color y fijadas (ver `06-inicio-y-notas.md`).
- **Recomendaciones:** motor de reglas que muestra sugerencias con su fuente; incluye la hora sugerida para dormir y despertar.
- **Estudio:** temporizador Pomodoro con tiempos editables y presets, visible en todas las pantallas mientras corre, registro del descanso y del tiempo libre entre pomodoros, historial con filtros y diez fichas de métodos de estudio con nivel de evidencia.
- **Cuentas:** modo invitado sin registro, cuentas con login y traspaso de los datos del invitado a la cuenta. Cada usuario ve solo sus datos.
- **Publicación:** desplegada en Render con base PostgreSQL en Neon (ver `04-stack-tecnologico.md`).

Pendiente de construir: cambio de `tareas.proyecto` por contexto, estadísticas semanales, tarjetas de repaso y exportación de datos desde la app.

## Decisiones de producto
- **El sueño no se registra a mano.** El sistema recomienda la hora de acostarse y despertar: estima la hora habitual de arranque con el primer bloque de los últimos 7 días (07:00 si no hay datos) y propone dormir 8 horas, dentro del rango que respalda el consenso AASM/SRS (7 horas o más).
- Hay muchas recomendaciones automáticas para ser menos ocioso, y cada una cita su fuente o se marca como sugerencia sin fuente. Ver `03-investigacion-estudio.md` y `05-ideas-de-funcionalidades.md`.

## Ideas futuras
- Registro automático de actividad (alta complejidad y privacidad).
- Repaso espaciado con tarjetas.
- Exportar datos.

## Requisitos no funcionales
- Uso personal y multiusuario: cada persona ve solo sus datos (al principio se pensó como uso local de una sola persona).
- Registro rápido: pocos clics.
- Funciona sin conexión si es posible.
- Los datos no deben perderse (respaldo/exportación).

## Modelo de datos preliminar
- **Categoría**: nombre, tipo (productiva / ocio / descanso).
- **Tarea**: título, proyecto, fecha límite, prioridad, estado.
- **Recordatorio**: fecha/hora, mensaje, tarea (opcional).
- **Bloque de tiempo**: inicio, fin, categoría, tarea (opcional), origen (manual / Pomodoro).
- **Contexto**: nombre, tipo (entorno / materia / tema), contexto padre.
- **Nota**: contenido, contexto (opcional), fecha (opcional), fijada.
- **Sesión de estudio**: contexto, tarea, tema, estilo, tiempos elegidos, estado.
- **Intervalo de estudio**: tipo (foco / descanso / libre), inicio, fin, planificado, completado, bloque de tiempo generado.

(La entidad Día, con hora de despertar y de dormir, se eliminó.)
