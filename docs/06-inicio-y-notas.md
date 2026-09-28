# 6. Pantalla de inicio y notas rápidas

> Decisión de producto de Matías (2026-09-28). La pantalla de inicio deja de ser solo un resumen de métricas y pasa a ser el lugar de trabajo del día.

## Pregunta guía
Cuando alguien entra, ¿qué necesita ver primero? Respuesta:
1. **Qué viene:** un calendario con las tareas, recordatorios y anotaciones futuras.
2. **Qué hago ahora:** un bloque para estudiar con Pomodoro u otro estilo de estudio.
3. **Dónde anoto algo ya:** notas rápidas que se pueden dirigir a un destino.

## Inicio propuesto
| Zona | Contenido | Datos |
|---|---|---|
| Calendario | Vista de mes/semana con tareas (por fecha límite), recordatorios y notas con fecha. Clic en un día abre su detalle. | `tareas`, `recordatorios`, `notas` |
| Estudiar ahora | Selector de estilo (Pomodoro 25/5, 50/10, personalizado), tarea o materia a la que se asocia y el temporizador. | `bloques_tiempo`, `contextos` |
| Nota rápida | Campo de texto siempre visible, con destino (a qué va) y fecha opcional. Enter guarda. | `notas`, `contextos` |
| Resumen del día | Las métricas actuales (tareas abiertas, horas aprovechadas, recordatorios). Pasan a segundo plano. | ya existe |

## Notas con destino
Una nota puede ir a un contexto: una materia, un tema o un entorno (carrera, vida cotidiana, proyectos personales).

**Modelo propuesto:** una sola tabla `contextos` con jerarquía, en vez de tres tablas.
- `contextos`: `nombre`, `tipo` (entorno / materia / tema), `contexto_padre_id` opcional, `color` opcional.
  - Ejemplo: Entorno "Carrera" → Materia "Programación 2" → Tema "Punteros".
- `notas`: `contenido`, `contexto_id` opcional (sin destino = bandeja de entrada), `fecha` opcional (para que aparezca en el calendario), `fijada` opcional.
- Las tareas y los bloques de tiempo podrían usar el mismo `contexto_id`, así el registro de horas se agrupa por materia. Hoy `tareas.proyecto` es texto libre: se reemplazaría por `contexto_id` en una migración nueva.

**Por qué una sola tabla:** los niveles pueden cambiar (a veces basta con Entorno → Materia) y agregar un tipo nuevo no exige otra tabla ni otras pantallas.

**Bandeja de entrada:** una nota sin destino queda en una bandeja para clasificarla después. Reduce la fricción al anotar, que es el objetivo de la nota rápida.

## Estilos de estudio para el selector
Se ofrecen como métodos con reglas simples, respaldados por lo ya investigado en `03-investigacion-estudio.md`:
- **Pomodoro:** 25 min de foco y 5 de descanso; pausa larga cada 4 ciclos.
- **Bloques largos:** 50 min y 10 de descanso, para tareas profundas.
- **Recuperación activa:** sesión de autoevaluación con apuntes cerrados (ligada a las tarjetas de repaso).
- **Personalizado:** foco y descanso a elección.
No se afirma que un estilo sea mejor que otro: se registra cuál se usó y con qué concentración, para que Matías lo compare con sus propios datos.

## Orden de construcción sugerido
1. [x] Módulos base (Tareas, Recordatorios, Registro).
2. [x] Contextos y notas rápidas (migración, modelo, campo en Inicio).
3. [x] Pomodoro y selector de estilos, con registro de bloques (vista Estudio).
4. [ ] Calendario en Inicio con tareas, recordatorios y notas.
5. [ ] Reemplazar `tareas.proyecto` por `contexto_id`.

## Preguntas abiertas
- ¿El calendario es mensual, semanal, o se alterna? (sugerencia: semanal por defecto en celular, mensual en pantallas grandes).
- ¿Se usa una librería de calendario (por ejemplo FullCalendar) o uno propio con Blade y CSS? La librería ahorra tiempo; uno propio da más control del estilo.
- ¿Las notas necesitan formato (negritas, listas) o alcanza con texto plano al principio?
- ¿Las notas se pueden editar y borrar desde el calendario?
