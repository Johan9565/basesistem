# Laravel Reverb — Configuración y despliegue

> Índice de documentación: [docs/README.md](./README.md)

Este proyecto usa **Laravel Reverb** como servidor WebSocket compatible con el protocolo Pusher, integrado con el **event broadcasting** de Laravel. Guía oficial: [Laravel Reverb (docs 13.x)](https://laravel.com/docs/13.x/reverb) y [Broadcasting](https://laravel.com/docs/broadcasting).

> El proyecto corre sobre **Laravel 12**; el comportamiento y las variables de entorno coinciden en esencia con la [guía de Reverb para Laravel 13.x](https://laravel.com/docs/13.x/reverb).

---

## Qué se configuró en el repositorio

| Elemento | Descripción |
|----------|-------------|
| **Paquete** | `laravel/reverb` en `composer.json` |
| **Configuración del broadcaster** | `config/broadcasting.php` — conexión `reverb` (driver `reverb`) |
| **Servidor Reverb** | `config/reverb.php` — host/puerto del proceso, apps, credenciales, `allowed_origins`, escalado Redis |
| **Canales** | `routes/channels.php` — ejemplo de canal privado `App.Models.User.{id}` |
| **Bootstrap de rutas** | `bootstrap/app.php` — `channels` apuntando a `routes/channels.php` (registra también la ruta `/broadcasting/auth` para canales privados/presence) |
| **Cliente** | `resources/js/bootstrap.js` — **Laravel Echo** + **pusher-js** (`Echo` usa el modo `reverb`) |
| **NPM** | `laravel-echo` y `pusher-js` en `package.json` |
| **Sail / Docker** | `compose.yaml` — el servicio `laravel.test` publica el puerto **8080** (`REVERB_SERVER_PORT`), coherente con el proceso `reverb:start` dentro del contenedor |

---

## Variables de entorno

Definidas en `.env` (plantilla en `.env.example`). Resumen:

### Aplicación y cliente (Vite)

- **`BROADCAST_CONNECTION=reverb`** — Laravel envía eventos al servidor Reverb.
- **`REVERB_APP_ID`**, **`REVERB_APP_KEY`**, **`REVERB_APP_SECRET`** — credenciales de la “aplicación” Reverb; deben coincidir entre servidor y cliente.
- **`REVERB_HOST`**, **`REVERB_PORT`**, **`REVERB_SCHEME`** — dirección que usa **Laravel (PHP)** para hablar con Reverb (API interna) y la que se refleja en la config de la app en `config/reverb.php`.
- **`VITE_REVERB_*`** — lo que verá el navegador vía Vite (`import.meta.env`). Deben apuntar al host/puerto/esquema por los que el **cliente** abre el WebSocket.

### Proceso del servidor Reverb

- **`REVERB_SERVER_HOST`** — interfaz donde escucha el proceso (por defecto `0.0.0.0` para aceptar conexiones dentro de Docker).
- **`REVERB_SERVER_PORT`** — puerto del proceso (en este proyecto **8080**, alineado con `compose.yaml`).

### Escalado (opcional, producción multi-instancia)

- **`REVERB_SCALING_ENABLED=true`** — varias instancias de Reverb comparten mensajes vía **Redis** (misma conexión Redis que usa la app).
- **`REVERB_SCALING_CHANNEL`** — nombre del canal Redis (por defecto `reverb`).

Generación inicial de claves y entradas en `.env`:

```bash
./vendor/bin/sail php artisan reverb:install
```

---

## Desarrollo local con Laravel Sail

### 1. Dependencias y assets

```bash
./vendor/bin/sail composer install
./vendor/bin/sail npm install
./vendor/bin/sail npm run build   # o npm run dev en otra terminal
```

### 2. Arrancar Reverb

En **otra** terminal (el proceso es de larga duración, como `queue:work`):

```bash
./vendor/bin/sail php artisan reverb:start
```

Útil para depuración:

```bash
./vendor/bin/sail php artisan reverb:start --debug
```

Tras cambios en código que afecten al servidor, reiniciar:

```bash
./vendor/bin/sail php artisan reverb:restart
```

### 3. Comportamiento típico con el `compose.yaml` actual

- La aplicación HTTP suele estar en **8000** (`FORWARD_APP_PORT`).
- **Reverb** escucha en **8080** dentro del contenedor y ese puerto está mapeado al host, así que desde el navegador en tu máquina suele usarse **`localhost:8080`** con `REVERB_SCHEME=http` en local.
- **`REVERB_HOST=localhost`** en `.env` encaja cuando el navegador y Vite corren en el mismo equipo que Docker (p. ej. WSL2 / Linux con puertos publicados).

Si accedieras a la app por otro hostname (p. ej. dominio local de Valet/Herd), **`REVERB_HOST` / `VITE_REVERB_HOST`** deberían ser ese mismo hostname para que el WebSocket no falle por mismatch de origen o DNS.

### 4. Orígenes permitidos (`allowed_origins`)

En `config/reverb.php` está configurado **`allowed_origins` => `['*']`**, práctico en desarrollo. En **producción** conviene restringir a tus dominios reales (ver más abajo).

---

## Frontend: Echo

En `resources/js/bootstrap.js`, Echo solo se instancia si existe **`VITE_REVERB_APP_KEY`**, para no romper entornos sin broadcasting.

Ejemplo de suscripción a un canal público (cuando definas eventos y canales):

```js
window.Echo.channel('orders').listen('OrderShipped', (e) => {
    console.log(e.order);
});
```

Canales **privados** / **presence** requieren usuario autenticado y la ruta **`/broadcasting/auth`** (ya registrada al cargar `routes/channels.php` vía `bootstrap/app.php`).

Documentación de broadcasting y eventos: [Broadcasting - Laravel](https://laravel.com/docs/broadcasting).

---

## Producción

### Separar “servidor Reverb” y “URL pública”

En producción es habitual:

- El **proceso** Reverb escucha en un host/puerto internos (p. ej. `0.0.0.0:8080`).
- El **navegador** se conecta por **HTTPS/WSS** a un hostname público (p. ej. `443`).

Por eso existen dos pares de conceptos (como describe la [documentación de Reverb](https://laravel.com/docs/13.x/reverb)):

- **`REVERB_SERVER_HOST` / `REVERB_SERVER_PORT`** — dónde corre el binario `php artisan reverb:start`.
- **`REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME`** — cómo Laravel y el cliente deben **publicar** la conexión (típicamente `REVERB_SCHEME=https`, `REVERB_PORT=443`, `REVERB_HOST=ws.tudominio.com`).

### Proxy inverso (Nginx)

Reverb usa rutas bajo **`/app`** (WebSocket) y **`/apps`** (API). El proxy debe:

- Usar **HTTP/1.1**, cabeceras **`Upgrade`** y **`Connection`** para WebSocket.
- Enviar **`Host`**, **`X-Forwarded-For`**, etc., como en la guía oficial.

Ejemplo orientativo (ajusta `proxy_pass` al socket interno de Reverb):

```nginx
location / {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Scheme $scheme;
    proxy_set_header SERVER_PORT $server_port;
    proxy_set_header REMOTE_ADDR $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_pass http://127.0.0.1:8080;
}
```

### TLS

- Lo habitual es terminar SSL en **Nginx/Caddy**, no en PHP.
- En desarrollo local con **Valet/Herd**, la documentación permite pasar **`--hostname`** a `reverb:start` y usar el certificado del sitio.

### Proceso supervisor

Reverb es un proceso largo; en producción debe ir bajo **Supervisor**, **systemd**, Docker, Kubernetes, etc., ejecutando:

```bash
php artisan reverb:start
```

Tras despliegues, usar **`php artisan reverb:restart`** para recargar de forma ordenada.

### Seguridad y límites

- Restringir **`allowed_origins`** en `config/reverb.php` a tus dominios.
- Revisar límites del SO (**open files**, `worker_connections` en Nginx, etc.) si esperas muchas conexiones concurrentes; la documentación de Reverb amplía estos puntos.
- **Escalado horizontal:** `REVERB_SCALING_ENABLED=true` y un **Redis** compartido entre instancias de Reverb.

### Build de front con Vite

Las variables **`VITE_*`** se sustituyen en **`npm run build`**. En CI/CD, define en el entorno de build los mismos valores públicos (`VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`, `VITE_REVERB_APP_KEY`) que usará el navegador en producción.

---

## Tests y CI

En `phpunit.xml` suele mantenerse **`BROADCAST_CONNECTION=null`** (o `log`) para no depender de Reverb en tests. No hace falta levantar el servidor WebSocket para la mayoría de pruebas unitarias/feature.

---

## Referencias

- [Laravel 13.x — Reverb](https://laravel.com/docs/13.x/reverb)
- [Laravel — Broadcasting](https://laravel.com/docs/broadcasting)
- [Laravel Sail](https://laravel.com/docs/sail)
