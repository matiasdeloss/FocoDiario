# Planner con contextos y toast de "Por ubicar"

**Objetivo:** eliminar el concepto "actividad" del planner semanal: siempre se habla de contextos. Además, confirmar con un toast cuando se agrega una tarjeta a "Por ubicar" en el calendario.

**Problema:** las "actividades" del planner son contextos de tipo materia con color (`ActividadController`, `Contexto::actividades()`), pero la interfaz las presenta como otra cosa, con su propio diálogo. Confunde y duplica la gestión de contextos.

**Decisiones del usuario (2026-10-05):**
- "Actividad" se renombra a "contexto" en toda la interfaz. Siempre va a ser contexto.
- Al agregar una tarjeta en "Por ubicar" del calendario aparece un toast de confirmación.

**Alcance:**
- Textos visibles del planner, la vista de día, el diálogo de caja, mensajes flash y de validación: "actividad(es)" → "contexto(s)".
- La caja elige un contexto de cualquiera de los cuatro tipos (no solo materias con color) y muestra su color efectivo, con la herencia de `HeredaColorDeContexto`.
- El diálogo de gestión del planner pasa a ser un atajo de contextos (crear y editar con tipo y color), coherente con la página Contextos.
- Toast de éxito al crear una tarjeta en "Por ubicar", usando el sistema de toasts estandarizado.

**Fuera de alcance:** renombrar identificadores internos que no se ven (`ColorActividad`, rutas `agenda.actividades.*`) salvo que hacerlo sea necesario; se decide en la implementación y se documenta.

**Restricciones:** rama `develop`; sin commits ni push hasta que el usuario lo pida. Empieza cuando termine `contexto-en-tareas` T8–T10 (tocan los mismos archivos).

**TDD:** off. Chequeos: `php artisan test`, `npm test`, `npm run build`.

## Tareas

- [ ] P1 — Renombrar "actividad" a "contexto" en toda la interfaz del planner y la agenda — ruta: delegada
- [ ] P2 — La caja admite cualquier contexto y usa su color efectivo — ruta: delegada
- [ ] P3 — Toast de confirmación al agregar tarjeta en "Por ubicar" — ruta: delegada
- [ ] P4 — Tests, revisión visual y documentación — ruta: delegada + verificación inline

## Relacionado

- Tableros múltiples y notas vinculadas: ver `odd/tasks/tableros-y-notas-vinculadas.md` (se hace después de esta feature).

## Progreso

- 2026-10-05: documento creado; espera a que termine `contexto-en-tareas` T8–T10.
