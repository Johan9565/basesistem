# Base Sistema

Stack: **Laravel 12**, **Vue 3 (Inertia SSR)**, **MongoDB**, **Redis**, **Laravel Sail**, **Reverb** (WebSockets), PHP 8.5.

## Documentación

Toda la documentación está en **`docs/`**. Empieza por el índice:

**[→ docs/README.md](docs/README.md)**

Ahí enlazamos la guía de instalación y permisos, notificaciones/broadcasting paso a paso, Reverb, módulo de componentes, Tailwind v4 y tablas (DaisyUI vs PrimeVue).

## Notificaciones (panel, API y tiempo real)

### Panel en el layout (campana)

- **Ubicación:** `resources/js/Components/NotificationBell.vue`, integrado en `AuthenticatedLayout.vue` (escritorio y móvil).
- **Comportamiento:** botón con badge de **no leídas del mes** (`auth.notification_unread_count` vía `HandleInertiaRequests`). Al abrir, un **Popover** de PrimeVue con:
  - filtro **Todas / No leídas**;
  - **Marcar todas como leídas** (solo no leídas del mes en curso);
  - listado del **mes en curso** (desde inicio de mes hasta ahora) con **VirtualScroller** en modo **lazy**, filas con **Skeleton** y carga por chunks contra la API.
- **Cada notificación** puede tener **varios enlaces**: un botón por enlace (`label` + `href`). El clic marca como leída (si aplica) y navega con Inertia a esa URL. Opcionalmente se muestra la línea **Ref:** con los **`item_ids`** guardados (contexto del negocio, no sustituye a `params` de ruta).

### API REST (autenticación `auth`)

| Método y ruta | Nombre Ziggy | Descripción |
|---------------|--------------|-------------|
| `GET /notifications/feed` | `notifications.feed` | Paginado: `offset`, `limit` (1–50), `filter` = `all` o `unread`. Respuesta: `total`, `items[]` con `id`, `message`, `item_ids`, `links[]`, `is_read`, fechas. |
| `PATCH /notifications/{notification}/read` | `notifications.read` | Marca una notificación como leída (solo del usuario actual). |
| `POST /notifications/mark-all-read` | `notifications.mark-all-read` | Marca todas las no leídas del mes en curso. |

Las peticiones JSON usan el **`csrf_token`** compartido por Inertia (`X-CSRF-TOKEN`).

### Modelo y persistencia (MongoDB)

- **`NotificationsModel`:** campos destacados `item_ids` (array), `links` (array de objetos con al menos `label` y `href`), `is_read`, `read_at`. Se mantienen `item_id` y `routes` / `route` para documentos antiguos.
- Al crear notificaciones conviene resolver URLs en servidor con **`App\Support\NotificationLinkResolver::resolve()`** y guardar el resultado en `links`, mientras al evento de broadcast puedes pasar las **definiciones** (con `route` + `params`) para que el cliente reciba enlaces ya resueltos en el payload.

### Evento `NotificacionToUser` (Reverb / Echo)

- Además de `message`, `userId`, `currentPaths` y `meta`, puedes enviar:
  - **`itemIds`:** lista de identificadores relacionados (el payload broadcast incluye `itemIds`; `itemId` string sigue admitido por compatibilidad).
  - **`links`:** lista de definiciones por enlace: string (URL), o array con **`href`**, **`url`**, o **`route` + `params`** (array asociativo al estilo Laravel, p. ej. `['user' => $id]`). El broadcast incluye **`links`** resueltos a `{ label, href }` y también **`urls`** / **`url`** (primera URL) para compatibilidad.
- El **toast** en `AuthenticatedLayout.vue` muestra **un botón por cada enlace** cuando el payload trae `links` (o deriva enlaces desde `url` / `urls` legados).

Configuración de Reverb, canales privados y `meta.inertia` / `currentPaths` siguen descritos en **[docs/NOTIFICACIONES-Y-BROADCASTING.md](docs/NOTIFICACIONES-Y-BROADCASTING.md)**.

## Arranque rápido (Sail)

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run build   # o npm run dev
./vendor/bin/sail php artisan reverb:start   # otra terminal, si usas notificaciones en tiempo real
```

App: `http://localhost:8000` (puerto por defecto `FORWARD_APP_PORT`).
