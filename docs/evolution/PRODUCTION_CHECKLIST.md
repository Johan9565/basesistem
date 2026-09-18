# Producción — checklist operativa

## Stack Dockge / Nginx

- [ ] Stack creado desde `docs/evolution/dockge-stack.compose.yaml`
- [ ] Contraseñas y `AUTHENTICATION_API_KEY` reemplazadas
- [ ] Contenedor en red `nginx-red` (+ red del stack Laravel si aplica)
- [ ] Host Nginx/NPM con SSL según `docs/evolution/nginx-evolution.conf`
- [ ] WebSockets habilitados en el proxy
- [ ] Volúmenes persistentes; reinicio sin pedir nuevo QR

## Backend Laravel

- [ ] JSON de cuenta de servicio en path seguro (`GOOGLE_SERVICE_ACCOUNT_PATH`)
- [ ] `GOOGLE_CALENDAR_ID` del calendario definitivo (compartido con la SA)
- [ ] `DEEPSEEK_API_KEY` de producción
- [ ] `EVOLUTION_BASE_URL` interna (`http://evolution-api:8080`) o pública HTTPS
- [ ] `EVOLUTION_API_KEY` = misma que `AUTHENTICATION_API_KEY` del stack Dockge
- [ ] `EVOLUTION_WEBHOOK_SECRET` fuerte; webhook envía header `X-Evolution-Secret`
- [ ] `composer require google/apiclient` (o `composer install`) en el servidor

## Alta de cada cliente

```bash
php artisan whatsapp:provision-client cliente_sistema_a \
  --calendar="CALENDAR_ID_DEL_CLIENTE" \
  --webhook="https://tudominio.com/api/evolution/webhook" \
  --timezone="America/Merida"
```

Luego:

1. `GET /instance/connect/cliente_sistema_a` → QR
2. Cliente escanea con WhatsApp Business
3. Verificar `connectionState`

## Monitoreo

- [ ] Dockge: RAM del contenedor ~150–300 MB en uso normal
- [ ] Reinicio del contenedor: sesión se restaura sin nuevo QR
- [ ] Reserva real desde teléfono externo → evento en Calendar + reply WhatsApp
- [ ] Logs Laravel sin 401 en webhook (secret correcto)
