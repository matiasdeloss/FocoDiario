<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>


# Diseño de FocoDiario

Guía de estilo que sale de las decisiones tomadas en el proyecto. Aplica a toda pantalla nueva o modificada. Los estilos base están en `resources/css/app.css` y `resources/css/organic.css` (tokens `--color-*`, `--radius-*`, `--font-*`); cada pantalla grande tiene su CSS aparte (`hoy.css`, `agenda.css`, `calendario.css`).

## Estilo general ("Organic")
- Superficies planas, cálidas y minimalistas: sin gradientes ni sombras (salvo popovers flotantes). Usar siempre los tokens, nunca colores sueltos.
- Tarjetas con radio de 18px y sin borde gris. La Agenda es la excepción: tarjetas de 24px con borde fino terracota (`--agenda-borde`), como el cuaderno.
- Títulos en la fuente de encabezado (Newsreader); cuerpo en la fuente base. En la Agenda, los títulos de caja van en MAYÚSCULAS con `letter-spacing` de 0.04em.
- Botón principal oscuro (`--color-accent-boton`), botones secundarios en pastilla (`--radius-pill`) con fondo `--color-bg`, sin borde.
- Enlaces "ver todos", "ver agenda" y "ver calendario": arriba a la derecha de la cabecera de cada card, en `--color-info-text`, con foco visible.
- Cuidar el contraste AA. Los colores de actividad se validan con `node tests/js/contraste-agenda.mjs`.

## Minimalismo
- No usar bordes de color a la izquierda en tarjetas ni eventos. El color se expresa con fondo suave y un punto de 6px (en tareas, el punto marca la prioridad).
- Rejillas y divisores muy tenues (`color-mix` con transparencia). Sin iconos decorativos donde el color ya comunica el tipo.
- Acciones secundarias (editar, borrar, más opciones) aparecen al pasar el mouse o al enfocar, y siempre visibles en pantallas táctiles (`@media (hover: none)`).
- Tarjetas compactas: no anchas ni grandes (tablero de tareas estilo Trello: columnas de ~288px).

## Interacción
- Estados y confirmaciones al instante (actualización optimista), pero la respuesta del servidor es la fuente de verdad. Bloquear el control mientras hay una petición en vuelo y serializar las peticiones por elemento; nunca dejar que una segunda respuesta pise a la primera.
- Al marcar una tarea como hecha en Hoy, su fila sale de la lista 300 ms después del clic; Hoy no lista tareas completadas.
- Modales de creación y edición con `<dialog>` nativo (`.dialogo`): errores 422 dentro del modal sin perder lo escrito, cierre con Esc, botón o clic afuera, foco atrapado y devuelto a quien lo abrió. Sin JS, siguen funcionando los formularios de siempre.
- Al tocar una card del calendario se abre un panel lateral derecho (hoja desde abajo bajo 992px) con su detalle: `role="dialog"` no modal, guardado automático con estado, eliminar con confirmación dentro del panel, Esc/botón/clic afuera lo cierran y devuelven el foco a la card; al tocar otra card solo cambia el contenido.
- Días vacíos del calendario: al pasar el mouse muestran un "+" para crear algo en ese día.
- El panel "Por ubicar" tiene un único scroll (la lista de tarjetas), sin scroll horizontal; los tres botones de creación (Tarea, Recordatorio, Nota) van en una sola línea en estilo pastilla.

## Scrolls
- Evitar scrolls anidados. Si un contenedor scrollea, que sea uno solo, con `overscroll-behavior: contain`, `scrollbar-width: thin` y `min-width: 0` en los hijos para que nada desborde en horizontal.
- En pantallas angostas los contenedores sticky pasan a `position: static` y la lista deja de scrollear por dentro.

## Accesibilidad y responsive
- Todo control con nombre accesible (`aria-label`), foco visible con `outline: 2px solid var(--color-accent)`, y `prefers-reduced-motion` respetado.
- Diseñar para el ancho de teléfono primero; sin scroll horizontal de página.

## Flujo de trabajo
- Verificar los cambios visuales renderizando la pantalla (Edge headless con `--screenshot`) y correr `php artisan test` antes de dar algo por terminado.
- Commits: solo título en una línea, en español, sin cuerpo ni coautor.
