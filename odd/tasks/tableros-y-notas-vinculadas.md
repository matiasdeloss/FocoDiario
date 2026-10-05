# Tableros múltiples y notas vinculadas a tareas

**Objetivo:** permitir varios tableros kanban por usuario, cada uno con una columna fija "Sin asignar" donde caen las tareas nuevas, y vincular notas a tareas para consultarlas desde la tarjeta.

**Problema:** hoy hay un único tablero implícito por usuario (`columnas_tablero.user_id`, sin entidad tablero) y toda tarea nueva cae en la primera columna "Pendiente". No se pueden separar ámbitos (facultad, trabajo, proyectos) ni asociar apuntes a una tarea: no existe relación entre notas y tareas.

**Decisiones del usuario (2026-10-05):**
- Varios tableros por usuario. Cada tablero tiene una primera columna fija **"Sin asignar"** que no se puede borrar; las tareas nuevas de ese tablero caen ahí.
- **Tablero principal (opción A):** uno de los tableros está marcado como principal; lo que se crea fuera del tablero (nota rápida de Hoy, calendario, formulario de tareas) va al "Sin asignar" del principal, y el formulario permite elegir otro tablero. La asociación tablero↔contexto (opción B) queda para más adelante.
- **Notas vinculadas (opción B):** una tarea puede tener notas vinculadas, visibles y abribles desde la tarjeta. Relación muchos a muchos (una nota puede servir a varias tareas).
- **Cambio posterior del mismo día: toda nota y toda tarea están en el tablero.** Las notas también son tarjetas: tienen columna y orden, caen en "Sin asignar" del tablero principal al crearse y se mueven por las columnas igual que las tareas, incluida "Completada". Un badge en la tarjeta distingue **Nota** de **Tarea**. El vínculo nota–tarea (opción B) se mantiene además.
- Pendiente de definir en la implementación (proponer al usuario): cómo se ve una nota que está en una columna de categoría "completada" en la pantalla Notas, en Hoy y en el calendario.

**Alcance:**
- Entidad `tableros` (usuario, nombre, principal, posición) y `columnas_tablero.tablero_id`. Migración que crea un tablero principal por usuario con sus columnas actuales y agrega "Sin asignar" a cada tablero, sin perder tareas ni columnas.
- Gestión de tableros: crear, renombrar, elegir principal, borrar (no el último; definir qué pasa con sus tareas al implementar y documentarlo) y cambiar de tablero en la vista.
- Columna "Sin asignar": primera, fija, no borrable; categoría de estado pendiente. La sincronización `estado` ↔ columna (`Tarea::booted`, `ColumnaTablero::paraEstado`) opera dentro del tablero de la tarea.
- Datos iniciales de usuarios nuevos (`DatosIniciales`) y fusión de invitado (`FusionarInvitado`) con tableros.
- Tabla pivote nota–tarea; vincular y desvincular notas desde el diálogo de la tarea; la tarjeta muestra cuántas notas tiene y permite abrirlas.
- Tests y documentación.

**Restricciones:** rama `develop`; sin commits ni push hasta que el usuario lo pida. Empieza después de `planner-contextos-y-toasts` (comparten tablero, calendario y diálogos). Aislamiento por usuario en tableros, columnas y vínculos.

**TDD:** off. Chequeos: `php artisan test`, `npm test`, `npm run build`.

## Tareas

- [ ] B1 — Modelo `Tablero`, migración de datos (principal + "Sin asignar") y sincronización de estado por tablero — ruta: delegada
- [ ] B2 — Vista del tablero con selector de tableros y gestión (crear, renombrar, principal, borrar) — ruta: delegada
- [ ] B3 — Tareas creadas fuera del tablero van al "Sin asignar" del principal; formulario con selector de tablero — ruta: delegada
- [ ] B4 — Notas vinculadas a tareas (pivote, diálogo de la tarea, indicador en la tarjeta) — ruta: delegada
- [ ] B6 — Notas como tarjetas del tablero: columna y orden en notas, migración que las ubica en "Sin asignar" del principal, arrastre entre columnas, badge Nota/Tarea, creación desde el tablero — ruta: delegada
- [ ] B5 — Datos iniciales, fusión de invitado, tests, documentación y revisión visual — ruta: delegada + verificación inline

## Progreso

- 2026-10-05: documento creado con las decisiones del usuario; espera a `planner-contextos-y-toasts`.
