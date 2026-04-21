# Notificaciones en tiempo real — Guía paso a paso

Flujo completo: **Laravel Reverb** + **Echo** + evento **`NotificacionToUser`** + **Inertia** + toasts PrimeVue y comportamiento por ruta (`/profile`, etc.).

> Infraestructura (Sail, `.env`, producción): [REVERB.md](./REVERB.md) y [Laravel Reverb](https://laravel.com/docs/13.x/reverb).

---

## Paso 1 — Requisitos en marcha

1. Variables en `.env`: `BROADCAST_CONNECTION=reverb`, credenciales `REVERB_*` y `VITE_REVERB_*` (tras `php artisan reverb:install`).
2. **Cola:** con `QUEUE_CONNECTION=sync` el broadcast se ejecuta en la misma petición. Si usas `database` u otro driver, ejecuta `php artisan queue:work`.
3. **Servidor WebSocket** (otra terminal con Sail):

   ```bash
   ./vendor/bin/sail php artisan reverb:start
   ```

4. **Assets:** `npm run dev` o `npm run build` para que exista `VITE_REVERB_APP_KEY` en el cliente.

---

## Paso 2 — Archivos del backend

| Archivo | Rol |
|---------|-----|
| `app/Events/NotificacionToUser.php` | Implementa `ShouldBroadcast`, canal privado `notifications_create_office.{userId}`, evento `.notificacion.to.user`. |
| `routes/channels.php` | Autoriza el canal: solo el usuario cuyo `id` coincide con el segmento del canal. |
| `bootstrap/app.php` | Ya registra `routes/channels.php` (incluye `/broadcasting/auth` para canales privados). |
| `config/broadcasting.php` | Conexión `reverb`. |
| Controlador (ej. `app/Http/Controllers/users.php`) | Tras la acción, `NotificacionToUser::dispatch(...)` con `userId` del **destinatario** (string, p. ej. MongoDB `getKey()`). |
| `app/Http/Controllers/NotificationsController.php` | API JSON del panel: `feed`, marcar leída, marcar todas (mes en curso). |
| `app/Support/NotificationLinkResolver.php` | Convierte definiciones de enlace (`route`+`params`, `url`, `href`) en `{ label, href }` para guardar o broadcast. |

---

## Paso 3 — Disparar el evento (PHP)

Ejemplo con **varios enlaces** e **`itemIds`** (recomendado). Cada entrada de `links` puede ser `route` + `params` (array asociativo como en `route()` de Laravel), `url`, `href` o un string (URL).

```php
use App\Events\NotificacionToUser;
use App\Support\NotificationLinkResolver;

$itemIds = [(string) $recurso->getKey()]; // contexto de negocio (opcional, varios valores)

$linkDefs = [
    ['label' => 'Mi perfil', 'route' => 'profile.edit', 'params' => []],
    ['label' => 'Usuarios', 'route' => 'users', 'params' => []],
];

NotificacionToUser::dispatch(
    message: 'Texto visible en el toast',
    userId: (string) $usuarioDestino->getKey(),
    itemIds: $itemIds,
    links: $linkDefs,
    currentPaths: ['/profile'],
    meta: [
        'inertiaGlobal' => [
            'only' => ['auth'],
            'preserveScroll' => true,
        ],
        'inertia' => [
            'only' => ['profileDisplay', 'mustVerifyEmail', 'status'],
            'preserveScroll' => true,
        ],
        'highlightDisplayKeys' => ['name', 'email'],
    ],
);
```

Compatibilidad: siguen admitidos **`url`** y **`urls`** (solo URLs); el payload broadcast incluye **`links`** resueltos y **`urls`** derivados.

- **`userId`**: destinatario del WebSocket.
- **`links`**: varios botones en el toast y en el panel (cada uno con su `router.visit(href)`). Los destinos deben ser rutas **GET** visitables con Inertia.

Persistencia en MongoDB: ver resumen en el [README del repositorio](../README.md#notificaciones-panel-api-y-tiempo-real) (`NotificationsModel`, `NotificationLinkResolver::resolve` para el campo `links`).

---

## Paso 4 — Archivos del frontend

| Archivo | Rol |
|---------|-----|
| `resources/js/bootstrap.js` | Crea `window.Echo` con `broadcaster: 'reverb'` si existe `VITE_REVERB_APP_KEY`. |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Suscripción `Echo.private(...)`, listener `.notificacion.to.user`, toast (varios botones si hay `links`), eventos globales, `router.reload` y highlight en perfil. |
| `resources/js/Components/NotificationBell.vue` | Panel campana: Popover, filtros, VirtualScroller lazy + Skeleton, API `notifications.feed`, botones por enlace. |
| `resources/js/composables/useNotificacionToUser.js` | Constantes de eventos y composable para reaccionar desde cualquier vista. |
| `resources/js/Pages/Profile/Edit.vue` | Escucha remarcado de campos tras actualización remota. |

---

## Paso 5 — `currentPaths`, datos globales y perfil

| Situación | Comportamiento |
|-----------|----------------|
| **Toast + botones de enlace** | Siempre que el payload pase la validación de destinatario (`recipientId` / canal privado). Un botón por cada elemento de `links` (o derivado de `url` / `urls`). |
| **Evento `app:notificacion-to-user`** | Siempre; el detalle incluye **`scopedContextMatched`**. |
| **`meta.inertiaGlobal`** | **`router.reload({ only: [...] })` en cualquier ruta** (p. ej. `['auth']` para actualizar nombre, rol, menú y permisos en el navbar). |
| **`meta.inertia` + remarcado** | Solo se **mezclan** en el mismo `reload` si la URL coincide con **`currentPaths`** (ej. `/profile`). |

El cliente hace **un solo** `reload` con `only` = unión sin duplicados de `inertiaGlobal.only` + `inertia.only` (esta última solo si aplica el contexto). Así, si estás en `/dashboard` recibes `auth` nuevo en el navbar; si además estás en `/profile`, también se piden `profileDisplay`, etc., y tras el reload se puede remarcar campos.

- **`currentPaths: []`**: `meta.inertia` se aplica en **cualquier** ruta (además de `inertiaGlobal`).
- **`currentPaths: ['/profile']`**: props de `meta.inertia` y highlight **solo** en perfil; `inertiaGlobal` sigue en toda la app.

Ejemplo en PHP (navbar + perfil):

```php
'meta' => [
    'inertiaGlobal' => ['only' => ['auth'], 'preserveScroll' => true],
    'inertia' => ['only' => ['profileDisplay', 'mustVerifyEmail', 'status'], 'preserveScroll' => true],
    'highlightDisplayKeys' => $highlightDisplayKeys,
],
```

---

## Paso 6 — Composable `useNotificacionToUser`

```js
import { useNotificacionToUser } from '@/composables/useNotificacionToUser';
import { router } from '@inertiajs/vue3';

useNotificacionToUser((payload) => {
    if (!payload.scopedContextMatched) {
        return;
    }
    if (payload.meta?.reloadUsers) {
        router.reload({ only: ['users', 'filters'], preserveScroll: true });
    }
});
```

Eventos exportados:

- `NOTIFICACION_TO_USER_EVENT` — `app:notificacion-to-user`
- `PROFILE_HIGHLIGHT_FIELDS_EVENT` — `app:profile-highlight-fields` (uso interno tras reload en perfil)

---

## Paso 7 — Flujo resumido (usuario editado desde administración)

1. Admin guarda cambios en `users.update`.
2. PHP calcula qué campos cambiaron y arma `highlightDisplayKeys` (mapeados a las claves de `profileDisplay`: `name`, `ape_pat`, `role`, `area`, `status`, etc.).
3. Se encola/emite `NotificacionToUser` hacia el canal del usuario editado.
4. Si ese usuario tiene la app abierta:
   - Ve el **toast** (y un botón por cada enlace en `links`, o el equivalente si solo hay `url`/`urls`).
   - Si está en **`/profile`**: Inertia recarga las props indicadas y se remarcan los bloques afectados unos segundos.

---

## Paso 8 — Comprobar que funciona

1. `reverb:start` activo y mismo `.env` en PHP y Vite.
2. Consola del navegador: sin errores de WebSocket; `window.Echo` definido.
3. Dos navegadores: admin en `/users`, usuario objetivo en `/profile` → al guardar, el segundo debe ver toast y, si aplica, datos nuevos + anillo en campos cambiados.

---

## Paso 9 — Tests

En `phpunit.xml` suele usarse `BROADCAST_CONNECTION=null` para no depender de Reverb en tests automatizados.

---

## Referencias cruzadas

- [Índice de documentación](./README.md)
- [Reverb: instalación y producción](./REVERB.md)
- [Guía general del proyecto](./GUIA-DEL-PROYECTO.md)
