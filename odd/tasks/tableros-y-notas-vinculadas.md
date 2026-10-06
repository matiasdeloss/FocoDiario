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

- [x] B1 — Modelo `Tablero`, migración de datos (principal + "Sin asignar") y sincronización de estado por tablero — ruta: delegada — hecho: `tableros` + `columnas_tablero.tablero_id/fija`, migración de datos (Principal + Sin asignar primera) con rollback; `paraEstado(estado, tablero)` y hook por tablero
- [x] B2 — Vista del tablero con selector de tableros y gestión (crear, renombrar, principal, borrar) — ruta: delegada — hecho: pestañas de tableros + gestión (crear, renombrar, principal, eliminar con destino) con formularios comunes; ?tablero= y último elegido en sesión
- [x] B3 — Tareas creadas fuera del tablero van al "Sin asignar" del principal; formulario con selector de tablero — ruta: delegada — hecho: hook: lo creado fuera va a Sin asignar del principal; selectores de tablero y columna (grupos) en diálogo, Hoy, notas; select de tablero en el formulario clásico
- [x] B4 — Notas vinculadas a tareas (pivote, diálogo de la tarea, indicador en la tarjeta) — ruta: delegada — hecho: pivote `nota_tarea`, `notas` en TareaRequest/diálogo (buscar y desvincular), contador y lista en la tarjeta
- [x] B6 — Notas como tarjetas del tablero: columna y orden en notas, migración que las ubica en "Sin asignar" del principal, arrastre entre columnas, badge Nota/Tarea, creación desde el tablero — ruta: delegada — hecho: notas con `columna_id`/`orden`, migración a Sin asignar del principal, tarjeta de nota con badge, mover (`notas.columna`), orden mezclado `tarea:`/`nota:`, alta rápida de nota
- [x] B5 — Datos iniciales, fusión de invitado, tests, documentación y revisión visual — ruta: delegada + verificación inline — hecho: DatosIniciales, FusionarInvitado (principal se une; otros tableros pasan a la cuenta), seeder, docs 02/04/06; `php artisan test` 453/453, `npm test` 26/26, build OK (revisión visual pendiente)

## Decisiones confirmadas por el usuario tras la implementación (2026-10-05)

1. Nota completada: atenuada y tachada en Notas, como hecha en el calendario. Solo se borra manualmente (nada automático).
2. Un único selector de columnas agrupadas por tablero (a criterio de implementación; se mantiene).
3. "+" al pie de columna crea en esa columna; "Nueva tarea" arriba crea en "Sin asignar". Se mantiene.
4. Cambio de tablero desde el selector del diálogo. Se mantiene.
5. **Cambio:** al reabrir una tarea o nota completada vuelve a la columna donde estaba antes de completarse (si ya no existe o es de otro tablero, a "Sin asignar" de su tablero).

## Correcciones pendientes de la revisión visual

- [x] B7 — Flechas de mover guardaban la columna siguiente (bug preexistente, también en `main`): corregido en `tablero.js`, verificado en pantalla (4 movimientos, PATCH correcto).
- [x] B8 — Reapertura a la columna previa (decisión 5) — ruta: delegada — hecho: `columna_previa_id` (FK nullOnDelete) en tareas y notas, se guarda al entrar en una completada y se olvida al salir; `ColumnaTablero::deReapertura/recordarPrevia`, `Nota::reabrir()`; fallback a "Sin asignar"/columna del estado del tablero actual; `ReaperturaColumnaPreviaTest` (6 tests); `php artisan test` 459/459
- [x] B9 (hecho sin revisión visual en pantalla; `npm test` 26/26, build OK) — Detalles visuales: popover de notas vinculadas tapa la tarjeta siguiente; hueco vacío en el diálogo de nota entre "Tablero y columna" y "Color"; buscador de notas vinculadas conserva el último texto; el alta rápida queda abierta tras guardar — ruta: delegada

## Progreso

- 2026-10-05: documento creado con las decisiones del usuario; espera a `planner-contextos-y-toasts`.
