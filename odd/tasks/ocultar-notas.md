# Ocultar notas de la pantalla Notas

**Objetivo:** poder ocultar notas de la pantalla Notas para despejarla, sin que desaparezcan del resto de la app.

**Decisión del usuario (2026-10-05):** una nota oculta deja de verse solo en la pantalla Notas; en la vista de contextos (y en el resto de la app) sigue apareciendo.

**Propuesta de comportamiento (por defecto, ajustable):**
- Acción "Ocultar" en cada nota de la pantalla Notas, con toast de confirmación y "Deshacer".
- Un filtro o enlace "Ver ocultas (N)" en la pantalla Notas para encontrarlas y la acción "Mostrar" para devolverlas.
- Se guarda en la nota (por ejemplo `notas.oculta_en_notas` booleano), no se borra nada.

**Restricciones:** rama `develop`; sin commits ni push hasta que el usuario lo pida. Va después de `tableros-y-notas-vinculadas` en la cola, salvo que el usuario la priorice.

**TDD:** off. Chequeos: `php artisan test`, `npm test`, `npm run build`.

## Tareas

- [ ] O1 — Columna y migración, acción ocultar/mostrar con toast y Deshacer — ruta: a definir
- [ ] O2 — Filtro "Ver ocultas" en Notas; la vista de contextos y el resto de la app no cambian — ruta: a definir
- [ ] O3 — Tests y revisión visual — ruta: a definir

## Progreso

- 2026-10-05: documento creado.
