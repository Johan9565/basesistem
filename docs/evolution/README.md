# Evolution API + DeepSeek + Google Calendar

Orquestación WhatsApp para agendar citas.

## Local vs producción

| Entorno | Qué usas |
|---------|----------|
| **Local (WSL)** | Solo Docker Compose. Sin Nginx, sin Dockge, sin SSL. Guía: [LOCAL_SETUP.md](LOCAL_SETUP.md) |
| **Producción** | Dockge + Nginx/SSL. Plantillas abajo + [PRODUCTION_CHECKLIST.md](PRODUCTION_CHECKLIST.md) |

## Docs

| Doc | Contenido |
|-----|-----------|
| [COMPOSER.md](COMPOSER.md) | Instalar `google/apiclient` |
| [GOOGLE_CALENDAR_SETUP.md](GOOGLE_CALENDAR_SETUP.md) | Cuenta de servicio y calendario |
| [LOCAL_SETUP.md](LOCAL_SETUP.md) | **WSL local**: QR, webhook Docker, E2E |
| [postman-webhook-payload.json](postman-webhook-payload.json) | Fixture Postman |
| [dockge-stack.compose.yaml](dockge-stack.compose.yaml) | Stack **producción** Dockge |
| [nginx-evolution.conf](nginx-evolution.conf) | Proxy SSL + WebSockets (**producción**) |
| [PRODUCTION_CHECKLIST.md](PRODUCTION_CHECKLIST.md) | Alta clientes y monitoreo (**producción**) |

## Flujo

`WhatsApp → Evolution → POST /api/evolution/webhook → Mongo + DeepSeek (tool) → Google Calendar → Evolution sendText → WhatsApp`
