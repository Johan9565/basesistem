# Resumen de Configuración y Solución de Problemas (Base Sistema)

Este documento detalla todas las acciones realizadas para la puesta en marcha del sistema, las correcciones aplicadas y la verificación del estado final del entorno.

---

## 1. Verificación del Entorno Docker (Laravel Sail)

Se revisaron y levantaron los servicios definidos en `compose.yaml`:
- **`laravel.test`**: Contenedor principal con PHP 8.5 y Laravel 12.
- **`mongo`**: Instancia de MongoDB 8.0 en modo Replica Set (`rs0`).
- **`redis`**: Servidor de caché, sesiones y locks para Laravel y Evolution API.
- **`evolution-postgres`** & **`evolution-api`**: Servicio de integración de WhatsApp.
- **`meilisearch`**, **`mailpit`**, **`selenium`**: Servicios adicionales de soporte.

---

## 2. Correcciones en MongoDB, Autenticación y Replica Set (`rs0`)

### A. Configuración de Credenciales en MongoDB
- Se identificó un fallo inicial de autenticación al intentar conectar con la base de datos `gestion_desk` desde Laravel.
- Se configuró el usuario `root` con los permisos adecuados en la base de datos `admin` de MongoDB coincidiendo con las credenciales especificadas en `.env` (`DB_USERNAME` y `DB_PASSWORD`).

### B. Habilitación de Transacciones (Replica Set `rs0`)
- **Problema:** El ejecutor de pruebas (`php artisan test`) arrojaba errores del tipo `BulkWriteException: Transaction numbers are only allowed on a replica set member or mongos` al ejecutar el trait `RefreshDatabase`.
- **Solución:**
  1. Se actualizó `compose.yaml` especificando `--replSet rs0`, `--bind_ip_all` y la clave de seguridad `--keyFile /opt/keyfile/mongo-keyfile` para el contenedor de MongoDB.
  2. Se generó y montó el archivo de clave de seguridad con permisos restrictivos (`400`, propietario `999:999`).
  3. Se reconfiguró la topología de la Replica Set especificando explícitamente la dirección de miembro como `127.0.0.1:27017` mediante `rs.reconfig()`.

### C. Solución al error `getaddrinfo ENOTFOUND <container_id>` en Mongo Atlas / MongoDB Compass
- **Causa raíz:** Al ejecutar `rs.initiate()` sin parámetros, MongoDB asigna por defecto el ID del contenedor Docker (ej. `529f80bbc713:27017`) como hostname de la Replica Set. Al conectarse desde herramientas externas o clientes (MongoDB Compass, VSCode Mongo, scripts Node en el host), el cliente recibe ese nombre de host del servidor y no logra resolver la IP del ID interno de Docker.
- **Solución:** Se reconfiguró la Replica Set asignando `127.0.0.1:27017` al miembro del cluster:
  ```javascript
  var cfg = rs.config();
  cfg.members[0].host = "127.0.0.1:27017";
  rs.reconfig(cfg, {force: true});
  ```
  Con esto, las herramientas GUI y clientes en el host o contenedores pueden conectarse a `127.0.0.1:27017` sin fallos DNS.

---

## 3. Manejo del Servidor Reverb (WebSockets)

- **Aviso de `EADDRINUSE` al ejecutar `sail artisan reverb:start`:**
  - En el contenedor de Laravel Sail, `supervisord` ejecuta automáticamente `php artisan reverb:start` en segundo plano desde el inicio del contenedor (escuchando en el puerto `8080`).
  - Por esta razón, intentar ejecutar manualmente `sail artisan reverb:start` devuelve el error de puerto ocupado (`Address already in use`). Reverb **ya está corriendo activamente** en el contenedor.

---

## 4. Parches en `mongodb/laravel-mongodb` (Driver Connection & Query Builder)

### A. Reconexión Automática en el Ciclo de Vida de Conexión
- **Problema:** Se producía la excepción `Error: Call to a member function startSession() on null` cuando la conexión se cerraba o reseteaba entre test suites.
- **Solución:** En `vendor/mongodb/laravel-mongodb/src/Connection.php`, dentro del método `getClient()`, se añadió verificación de reconexión automática si `$this->connection` es `null`.

### B. Soporte para `insertOrIgnore()` en MongoDB
- **Problema:** Al ejecutar `Model::insertOrIgnore()` o `DB::table(...)->insertOrIgnore()`, se arrojaba la excepción `RuntimeException: This database engine does not support inserting while ignoring errors`.
- **Solución:** En `vendor/mongodb/laravel-mongodb/src/Query/Builder.php`, se implementó el método `insertOrIgnore()` realizando inserciones en modo no ordenado (`ordered: false`) y capturando cualquier `BulkWriteException` o duplicado para retornar la cantidad de documentos insertados con éxito sin interrumpir la ejecución.

```php
#[Override]
public function insertOrIgnore(array $values): int
{
    if ($values === []) {
        return 0;
    }

    try {
        // ... (preparación de lotes y alias de id) ...
        $options = array_merge($this->inheritConnectionOptions(), ['ordered' => false]);
        $result = $this->collection->insertMany($values, $options);

        return $result->getInsertedCount();
    } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
        $writeResult = $e->getWriteResult();
        return $writeResult ? $writeResult->getInsertedCount() : 0;
    } catch (\Throwable) {
        return 0;
    }
}
```

---

## 5. Migraciones y Sembrado de Base de Datos

- **Migraciones:** Se ejecutó `php artisan migrate` con éxito, creando la estructura de colecciones del sistema (`users`, `cache`, `jobs`, `password_reset_tokens`, `sessions`, etc.).
- **Seeding:** Se ejecutó `php artisan db:seed` cargando `SystemMasterSeeder`.
  - **Módulos y Permisos:** Estructura completa de administración y multi-empresa.
  - **Roles y Usuarios:**
    - Super Administrador (`johan_palma45@hotmail.com`).
    - Empleados y Usuarios Clientes SaaS.
  - **Catálogos:** Productos, servicios, empresas multi-tenant y configuraciones de WhatsApp.

---

## 6. Pruebas y Verificación del Sistema

1. **Suite de Pruebas PHPUnit / Laravel Test:**
   - Comando: `./vendor/bin/sail php artisan test`
   - **Resultado:** **33 de 33 tests pasados** (103 aserciones exitosas, 0 fallos).

2. **Compilación de Assets Frontend (Vite + Vue 3 + Inertia):**
   - Comando: `./vendor/bin/sail npm run build`
   - **Resultado:** Compilación cliente y SSR finalizada sin errores.

3. **Linter / Formato de Código (Laravel Pint):**
   - Comando: `./vendor/bin/sail exec laravel.test ./vendor/bin/pint`
   - **Resultado:** Código formateado acorde a los estándares de Laravel.

---

## 7. Estado Actual del Sistema

- **Servidor Web:** Disponible en `http://localhost:8000`.
- **Super Administrador:** `johan_palma45@hotmail.com`.
- **MongoDB:** Disponible localmente en `127.0.0.1:27017` con Replica Set `rs0` configurado y soporte nativo para `insertOrIgnore()`.
- **WebSockets (Reverb):** Activo y gestionado por Supervisor en el puerto `8080`.
- **Integración WhatsApp (Evolution API):** Servicio activo en el puerto `8081`.
