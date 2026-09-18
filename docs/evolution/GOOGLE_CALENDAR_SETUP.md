# Google Calendar — configuración local (Cuenta de Servicio)

## 1. Proyecto y API

1. Entra a [Google Cloud Console](https://console.cloud.google.com/).
2. Crea un proyecto (o selecciona uno existente).
3. Ve a **APIs y servicios → Biblioteca** y habilita **Google Calendar API**.

## 2. Cuenta de Servicio

1. **IAM y administración → Cuentas de servicio → Crear cuenta de servicio**.
2. Nombre sugerido: `whatsapp-calendar-local`.
3. En la cuenta creada: **Claves → Agregar clave → JSON**.
4. Descarga el archivo y guárdalo en el proyecto Laravel como:

```text
storage/app/google/service-account.json
```

Esa ruta ya está ignorada por git (solo se versiona `.gitignore` dentro de la carpeta).

## 3. Calendario de pruebas

1. Abre [Google Calendar](https://calendar.google.com/).
2. Crea un calendario (ej. `Citas pruebas`).
3. En **Configuración del calendario → Compartir con determinadas personas**, agrega el **correo de la Cuenta de Servicio** (termina en `@*.iam.gserviceaccount.com`).
4. Permiso: **Hacer cambios en los eventos**.
5. Copia el **ID del calendario** (suele ser un email o un ID largo en la sección “Integrar calendario”).

## 4. Variables en `.env`

```env
GOOGLE_CALENDAR_ID=tu-calendar-id@group.calendar.google.com
GOOGLE_SERVICE_ACCOUNT_PATH=storage/app/google/service-account.json
GOOGLE_CALENDAR_TIMEZONE=America/Merida
```

## 5. Paquete PHP

Desde la raíz del proyecto (o dentro de Sail), instala el SDK oficial:

```bash
composer require google/apiclient
```

Sin este paquete, `GoogleCalendarService` no podrá autenticarse ni crear eventos.
