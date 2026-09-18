# Prueba local (WSL + Docker) — sin Nginx ni Dockge

Todo corre en el proyecto Compose **`basesistem`** (mismo stack y misma Mongo con tus datos).

| Pieza | Cómo se alcanza |
|-------|-----------------|
| Evolution (desde WSL/host) | `http://localhost:8081` |
| Evolution (entre contenedores) | `http://evolution-api:8080` |
| Laravel | DNS `http://laravel.test` (contenedor `basesistem-laravel.test-1`) |
| Webhook | `http://laravel.test/api/evolution/webhook` |

## 0. Arranque (un solo stack)

```bash
cd /var/www/html/basesistem
docker compose up -d
docker compose logs -f evolution-api
```

Si quedó un stack viejo `andres_page` por error:

```bash
docker compose -p andres_page down
```

Confirma en `.env`:

- `EVOLUTION_API_KEY` / `EVOLUTION_WEBHOOK_SECRET`
- `EVOLUTION_BASE_URL=http://evolution-api:8080`
- `DEEPSEEK_API_KEY`
- `GOOGLE_CALENDAR_ID` + JSON en `storage/app/google/service-account.json`
- `composer require google/apiclient`

```bash
docker compose exec laravel.test php artisan whatsapp:seed-instance instancia_local --calendar="TU_CALENDAR_ID"
```

## 1. Crear instancia + QR

```bash
curl -X POST "http://localhost:8081/instance/create" \
  -H "apikey: change-me-evolution-key" \
  -H "Content-Type: application/json" \
  -d '{
    "instanceName": "instancia_local",
    "qrcode": true,
    "integration": "WHATSAPP-BAILEYS",
    "webhook": {
      "url": "http://laravel.test/api/evolution/webhook",
      "byEvents": false,
      "base64": false,
      "events": ["MESSAGES_UPSERT"],
      "headers": {
        "X-Evolution-Secret": "change-me-webhook-secret"
      }
    }
  }'
```

```bash
curl -X GET "http://localhost:8081/instance/connect/instancia_local" \
  -H "apikey: change-me-evolution-key"
```

```bash
curl -X GET "http://localhost:8081/instance/connectionState/instancia_local" \
  -H "apikey: change-me-evolution-key"
```

## 2. Prueba de webhook aislada

```bash
docker compose exec evolution-api wget -qO- \
  --header='Content-Type: application/json' \
  --header='X-Evolution-Secret: change-me-webhook-secret' \
  --post-data='{"event":"MESSAGES_UPSERT","instance":"instancia_local","data":{"key":{"remoteJid":"5219981234567@s.whatsapp.net","fromMe":false,"id":"TEST1"},"message":{"conversation":"Agéndame cita el viernes a las 10"}}}' \
  http://laravel.test/api/evolution/webhook
```

## 3. Prueba de fuego

Mensaje WhatsApp → logs Laravel → Mongo → DeepSeek → Calendar → reply.

```bash
docker compose exec laravel.test php artisan whatsapp:provision-client instancia_local \
  --calendar="TU_CALENDAR_ID" \
  --webhook="http://laravel.test/api/evolution/webhook"
```
