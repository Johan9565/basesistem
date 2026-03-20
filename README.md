# Base Sistema — Laravel 12 + Vue + MongoDB + Sail

Stack: **Laravel 12**, **Vue 3 (Inertia SSR)**, **MongoDB**, **Redis**, **Meilisearch**, **Mailpit**, **Reverb**, PHP 8.5.

---

## Requisitos

- Docker + Docker Compose
- Sail (`./vendor/bin/sail`)

---

## Instalación desde cero

```bash
curl -s "https://laravel.build/basesistem?with=redis,meilisearch,mailpit,selenium" | bash
cd basesistem
```

### 1. Configurar el `.env`

Asegurarse de que `APP_NAME` lleve comillas si contiene espacios:

```
APP_NAME="Base Sistema"
```

Variables obligatorias para Docker Sail (agregar si no existen):

```
WWWGROUP=1000
WWWUSER=1000
FORWARD_APP_PORT=8000
FORWARD_REDIS_PORT=6379
FORWARD_MEILISEARCH_PORT=7700
FORWARD_MAILPIT_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025
VITE_PORT=5173
```

Variables de MongoDB:

```
DB_CONNECTION=mongodb
DB_HOST=mongo
DB_PORT=27017
DB_DATABASE=template
DB_USERNAME=root
DB_PASSWORD=<tu_password>
```

### 2. Build del Dockerfile personalizado

```bash
./vendor/bin/sail build --no-cache
```

### 3. Levantar contenedores

```bash
./vendor/bin/sail up -d
```

### 4. Instalar dependencias MongoDB y Breeze

```bash
./vendor/bin/sail composer require mongodb/laravel-mongodb
./vendor/bin/sail composer require laravel/breeze --dev
./vendor/bin/sail php artisan breeze:install vue --ssr --dark
./vendor/bin/sail npm install --legacy-peer-deps
```

### 5. Compilar assets (obligatorio en producción / primer uso)

```bash
./vendor/bin/sail npm run build
```

> **Nota:** Sin este paso el servidor responde HTTP 500 por `Vite manifest not found`.

### 6. Enlazar storage y limpiar caché

```bash
./vendor/bin/sail php artisan storage:link
./vendor/bin/sail php artisan config:clear
./vendor/bin/sail php artisan view:clear
```

### 7. En `app/Models/User.php`

```php
use MongoDB\Laravel\Auth\User as Authenticatable;
```

---

## Puertos expuestos

| Servicio      | Puerto local |
|---------------|-------------|
| Laravel (app) | 8000        |
| Vite (dev)    | 5173        |
| Reverb WS     | 8080        |
| MongoDB       | 27017       |
| Redis         | 6379        |
| Meilisearch   | 7700        |
| Mailpit UI    | 8025        |
| Mailpit SMTP  | 1025        |

---

## URLs

- App: http://localhost:8000
- Mailpit: http://localhost:8025
- Meilisearch: http://localhost:7700

---

## Errores frecuentes

### `Failed to parse dotenv file. Encountered unexpected whitespace at [Base Sistema]`
El valor de `APP_NAME` contiene espacios. Usar comillas: `APP_NAME="Base Sistema"`.

### `Vite manifest not found at: public/build/manifest.json`
Los assets no están compilados. Ejecutar: `./vendor/bin/sail npm run build`

### `Database connection [mongodb] not configured`
El paquete está instalado pero falta registrar la conexión en `config/database.php`. Agregar dentro del array `connections`:

```php
'mongodb' => [
    'driver' => 'mongodb',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', 27017),
    'database' => env('DB_DATABASE', 'laravel'),
    'username' => env('DB_USERNAME', ''),
    'password' => env('DB_PASSWORD', ''),
    'options' => [
        'database' => env('DB_AUTHENTICATION_DATABASE', 'admin'),
    ],
],
```

Luego limpiar caché: `./vendor/bin/sail php artisan config:clear`

---

## Desarrollo con Vite (hot reload)

```bash
./vendor/bin/sail npm run dev
```

---

## Tiempo real (Laravel Reverb)

La app incluye **Reverb** para WebSockets y broadcasting. Instalación de credenciales, arranque del servidor con Sail, variables de entorno y despliegue en producción están descritas en **[docs/REVERB.md](docs/REVERB.md)** (basado en la [documentación oficial de Reverb](https://laravel.com/docs/13.x/reverb)).

---

## Sistema de Roles y Permisos

### Arquitectura general

```
users.role_id (ObjectId)
       ↓
roles.permissions[] → { id: ObjectId, name: string }
       ↓  pluck('id') → cast a string
permissions WHERE _id IN [...] AND status=1 → pluck('module') → ["dashboard", ...]
       ↓  whereIn('route', slugs)
modules WHERE route IN [...] AND status=1 → ordenados por order_index
       ↓
HandleInertiaRequests → auth.menu (navbar) + auth.can (v-if en Vue)
```

---

### Colecciones en MongoDB

#### `users`
```json
{
  "_id": ObjectId("..."),
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "password": "...",
  "role_id": ObjectId("67b4e24bc841f5b5c5ed6b32")
}
```

#### `roles`
```json
{
  "_id": ObjectId("67b4e24bc841f5b5c5ed6b32"),
  "name": "super-admin",
  "role": "Super Administrador",
  "status": 1,
  "permissions": [
    { "id": ObjectId("66feca817019ff65de743bca"), "name": "dashboard" },
    { "id": ObjectId("66feca817019ff65de743bcb"), "name": "users_system" }
  ]
}
```
> El campo `id` dentro de cada objeto del array es **ObjectId** y referencia a `permissions._id`. El campo `name` es decorativo — sirve para identificar visualmente en la base de datos a qué permiso pertenece. La búsqueda real siempre se hace por `id`.

#### `permissions`
```json
{
  "_id": ObjectId("66feca817019ff65de743bca"),
  "name": "dashboard",
  "description": "Apartado Dashboard",
  "status": 1,
  "icon": "<i class=\"fas fa-home\"></i>",
  "module": "dashboard"
}
```
> `module` es el slug que conecta con la colección `modules`. Si `status` es `0` el permiso se ignora aunque el rol lo tenga asignado.

#### `modules`

Módulo standalone (link directo):
```json
{
  "_id": ObjectId("..."),
  "name": "Inicio",
  "route": "dashboard",
  "relation": "NULL",
  "order_index": 1,
  "status": 1
}
```

Módulo dropdown padre (no lleva a ningún lado, despliega hijos):
```json
{
  "_id": ObjectId("..."),
  "name": "Administración",
  "route": "administration",
  "relation": 0,
  "order_index": 5,
  "status": 1
}
```

Módulo hijo de un dropdown:
```json
{
  "_id": ObjectId("..."),
  "name": "Usuarios",
  "route": "users.index",
  "relation": "administration",
  "order_index": 1,
  "status": 1
}
```

> `relation: "NULL"` → link standalone con ruta Ziggy propia.
> `relation: 0` → dropdown padre, sin ruta Laravel, despliega sus hijos en el navbar.
> `relation: "administration"` → hijo del dropdown cuyo `route` es `"administration"`.
> `route` debe coincidir con `module` del permiso correspondiente. `order_index` determina el orden.

---

### Flujo completo por request

```
1. Request entra → HandleInertiaRequests::share()
2. $user = $request->user()
3. $role = $user->role_data()->first()
   └─ belongsTo(RoleModel, 'role_id') → busca roles WHERE _id = user.role_id
4. Extrae los ObjectId del array permissions del rol → pluck('id') → cast a string
5. PermissionsModel::whereIn('_id', $ids)->where('status', 1)->pluck('module')
   └─ ["dashboard", "users_system", ...]
6. ModulesModel::whereIn('route', $slugs)->where('status', 1)->orderBy('order_index')
   └─ colección de módulos para el navbar
7. Entrega a Vue via Inertia:
   - auth.menu  → módulos del navbar ordenados
   - auth.can   → ["dashboard", "users_system", ...]
   - auth.role  → "Super Administrador"
   - auth.user  → datos del usuario autenticado
```

---

### Archivos involucrados

| Archivo | Responsabilidad |
|---|---|
| `app/Models/User.php` | Relación `role_data()` y método `hasPermission()` |
| `app/Models/RoleModel.php` | Colección `roles` |
| `app/Models/PermissionsModel.php` | Lista maestra de acciones |
| `app/Models/ModulesModel.php` | Lista maestra del navbar |
| `app/Http/Middleware/HandleInertiaRequests.php` | Construye el menú y permisos para Vue en cada request |
| `app/Http/Middleware/CheckPermission.php` | Protege rutas en el backend |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Pinta el navbar con `auth.menu` |

---

### Reglas importantes al definir modelos MongoDB

Siempre declarar **tanto** `$collection` como `$table` con el mismo valor. Si solo se declara `$collection`, MongoDB Laravel ignora el valor y usa el nombre de la clase pluralizado:

```php
protected $connection = 'mongodb';
protected $collection = 'roles'; // para el driver MongoDB
protected $table      = 'roles'; // para que Eloquent lo respete
```

No usar cast `'array'` en campos que MongoDB ya devuelve como array (como `permissions`). MongoDB nativamente devuelve arrays — el cast intentará hacer `json_decode` sobre un array y lanzará un `TypeError`.

En MongoDB Laravel, acceder a una relación como propiedad (`$user->role_data`) devuelve el **builder** (BelongsTo), no el resultado. Siempre llamar `.first()` explícitamente:

```php
// MAL — devuelve el BelongsTo builder, no el modelo
$role = $user->role_data;

// BIEN — devuelve el RoleModel o null
$role = $user->role_data()->first();
```

---

### Protección de rutas

Siempre encadenar en este orden. El `dashboard` no lleva `permission:` porque es la ruta de fallback a donde redirige `CheckPermission` cuando falla:

```php
// routes/web.php

// Dashboard — accesible para cualquier usuario autenticado
Route::get('/dashboard', fn() => Inertia::render('Dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Módulos específicos — con permission:
Route::get('/usuarios', [UserController::class, 'index'])
    ->middleware(['auth', 'permission:users_system'])
    ->name('users.index');

// Agrupar varias rutas del mismo módulo
Route::middleware(['auth', 'permission:viaticos'])->group(function () {
    Route::get('/viaticos',        [ViaticosController::class, 'index'])->name('viaticos.index');
    Route::post('/viaticos',       [ViaticosController::class, 'store'])->name('viaticos.store');
    Route::patch('/viaticos/{id}', [ViaticosController::class, 'update'])->name('viaticos.update');
});
```

> El string después de `:` debe coincidir exactamente con el campo `module` del documento en la colección `permissions`.

> Nunca usar `permission:` sin `auth` antes. Si `CheckPermission` recibe un usuario null, redirige al login.

---

### Verificación puntual en controladores

```php
public function index(Request $request)
{
    if (!$request->user()->hasPermission('users_system')) {
        abort(403);
    }
}
```

---

### Acceso a permisos en Vue

Disponibles globalmente en cualquier componente o página Inertia sin necesidad de pasarlos como props:

```js
// En <script setup>
import { usePage, computed } from '@inertiajs/vue3'

const page = usePage()
const can  = computed(() => page.props.auth.can ?? [])
const role = computed(() => page.props.auth.role)

if (can.value.includes('users_system')) { ... }
```

```html
<!-- En el template -->
<div v-if="$page.props.auth.can.includes('users_system')">
    Solo visible con ese permiso
</div>
```

> El frontend solo oculta elementos de la UI. Un usuario puede saltarse la UI y hacer peticiones HTTP directas. Siempre proteger también en el backend con el middleware `permission:` o con `hasPermission()` en el controlador.

---

### Dos capas de protección

| Capa | Herramienta | Cuándo se ejecuta | Protege |
|---|---|---|---|
| Backend | middleware `permission:slug` | en cada request HTTP | la ruta y el dato real |
| Backend | `$user->hasPermission('slug')` | dentro del controlador | lógica específica |
| Frontend | `auth.can.includes('slug')` en Vue | al renderizar | lo que se muestra en pantalla |

Las tres son complementarias. Nunca depender solo del frontend.
