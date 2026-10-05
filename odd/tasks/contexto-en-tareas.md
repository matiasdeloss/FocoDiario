# Contexto en las tareas

**Objetivo:** que cada tarea se asigne a un contexto (entorno, materia, tema o proyecto) en lugar del texto libre `tareas.proyecto`, para agrupar tareas, notas, sesiones de estudio y agenda bajo la misma clasificación.

**Problema:** `tareas.proyecto` es texto libre y no se vincula con los contextos que ya usan notas, sesiones de estudio y cajas de la agenda. "Prog II" y "Programación II" quedan como dos proyectos distintos, y no se puede ver todo lo de una materia junto.

**Decisiones del usuario (2026-10-05):**
- Reemplazar `tareas.proyecto` por `tareas.contexto_id` (no conviven los dos campos).
- Agregar `proyecto` como cuarto tipo de contexto (`TipoContexto::Proyecto`), junto a entorno, materia y tema.

**Alcance:**
- Enum, creación y edición de contextos con el tipo nuevo.
- Migración que crea `contexto_id` en `tareas`, convierte cada texto de `proyecto` existente en un contexto del mismo usuario (reutiliza uno con el mismo nombre si existe; si no, crea uno de tipo `proyecto`) y elimina la columna `proyecto`. Debe funcionar en SQLite, MySQL y PostgreSQL.
- Tareas: formulario, lista y filtros, nota rápida de Hoy, tablero (filtro y etiqueta de la tarjeta), tarjetas del calendario y cualquier otro uso de `proyecto`.
- Fusión de invitado a cuenta (`FusionarInvitado`) sigue funcionando con tareas que apuntan a contextos.
- Tests y documentación (`docs/02`, `docs/04`, `docs/06`).

**Fuera de alcance:** estadísticas por contexto, cambios en el registro de horas.

**Restricciones:**
- Rama `develop`. Sin commits ni push hasta que el usuario lo pida (regla del usuario por encima del commit por tarea de ODD).
- Aislamiento por usuario: el contexto elegido debe pertenecer al usuario (`ReglasDeUsuario::existe`).

**TDD:** off (sin configuración de TDD en el proyecto; mismo criterio que las features anteriores). Chequeos: `php artisan test`, `npm test`, `npm run build`.

## Tareas

- [x] T1 — Tipo `proyecto` en contextos (enum, etiquetas, formularios y validación) — ruta: delegada (writer único, varios archivos) — hecho: enum `Proyecto`, factory `proyecto()`, formulario y validación de contextos; test de alta del tipo
- [x] T2 — Migración `proyecto` → `contexto_id` con conversión de datos y modelo `Tarea` — ruta: delegada (mismo writer) — hecho: migración 2026_10_10_000001 + `ConvertirProyectosEnContextos`; modelo `Tarea::contexto()`; 3 tests de migración (conversión, prioridad raíz, down)
- [x] T3 — Formularios, filtros, tablero, nota rápida, calendario y servicios usan contexto — ruta: delegada (mismo writer) — hecho: formulario, lista, tablero, nota rápida (`tarea_contexto_id`), calendario y servicios usan contexto; `FusionarInvitado` sin cambios (los ids de contexto se conservan)
- [x] T4 — Tests actualizados y nuevos (migración de datos, aislamiento, filtros) y documentación — ruta: delegada (mismo writer) — hecho: tests actualizados y nuevos; docs 02/04/06; `php artisan test` 400/400, `npm test` 19/19
- [ ] T5 — Verificación del orquestador: suite completa, build y revisión visual en local — ruta: inline. Parcial: `php artisan test` 400/400 y `npm run build` OK re-ejecutados por el orquestador; migración revisada (copia antes de borrar la columna). Revisión visual hecha sobre una copia SQLite de la base demo (migración OK, 26/26 tareas con contexto): herencia de color correcta en tablero y notas, claro y oscuro. Observaciones: (a) en celular la tarjeta de ayuda de contextos ocupa ~55% de la pantalla; (b) en oscuro el color propio "arena" casi no se distingue (preexistente, no lo causa esta feature); (c) el diálogo no indica que la tarjeta hereda el color.
- [x] T6 — Explicar en la vista de contextos para qué sirven y qué es cada tipo (pedido del usuario) — ruta: delegada (mismo writer) — hecho: bloque explicativo + leyenda de los 4 tipos y estado vacío en `contextos/index`; test en `ContextoTest`
- [x] T7 — Notas y tareas respetan el color de su contexto — ruta: delegada (mismo writer). Decisión del usuario: gana el color propio; el del contexto es el color por defecto cuando la nota o tarea no tiene uno. Si el contexto no tiene color, se usa el del ancestro más cercano que tenga. — hecho: `colorVisible()` en Tarea y Nota (trait `HeredaColorDeContexto`, `ColoresDeContexto`, `ColorVisible`); tablero y notas lo usan; `ColorHeredadoDelContextoTest` (8 tests); `php artisan test` 409/409, `npm test` 19/19, build OK

- [x] T8 — Ayuda de contextos plegable ("¿Para qué sirven los contextos?"): abierta sin contextos, cerrada si ya hay — ruta: delegada (mismo writer) — hecho: details/summary con estado inicial según haya contextos; test actualizado
- [x] T9 — Paleta única de 10 colores (`ColorActividad`) para notas, tareas y contextos, con migración de los colores guardados de la paleta vieja de 5 — ruta: delegada (mismo writer). Decisión del usuario 2026-10-05. — hecho: `ColorNota` eliminado; notas/tareas/contextos guardan el hex de `ColorActividad`; migración 2026_10_11_000001 con mapeo documentado; pickers, validación, seeders y CSS migrados; ciruela y oliva oscuros ajustados
- [x] T10 — Opción "Sin color" explícita en todos los selectores, que significa heredar del contexto (o del padre, en contextos), con aviso del color heredado en el diálogo — ruta: delegada (mismo writer). Decisión del usuario 2026-10-05. — hecho: "Sin color" primero en todos los selectores; `ColoresDeContexto::visible()`; punto heredado en el árbol de contextos; pista "Usa el color de …" (color-heredado.js + test JS); `php artisan test` 417/417, `npm test` 22/22, build OK

## Criterios de aceptación

- Al crear o editar una tarea (formulario, nota rápida, tablero) se elige un contexto opcional del usuario, de cualquiera de los cuatro tipos.
- El tablero y la lista filtran por contexto; la tarjeta muestra el nombre del contexto.
- Las tareas existentes con `proyecto` quedan asociadas a un contexto con ese nombre después de migrar.
- No queda ninguna referencia a `tareas.proyecto` en el código.
- No se puede asignar un contexto de otro usuario.
- Pasan `php artisan test`, `npm test` y `npm run build`.

## Progreso

- 2026-10-05: documento creado.
- 2026-10-05: T1–T4 implementadas por el writer delegado (sin commit); pendiente T5 (verificación del orquestador).
