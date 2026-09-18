# Dependencia Composer requerida

Este módulo usa el SDK oficial de Google Calendar.

**Debes instalarlo tú** (el agente no ejecuta Composer en este proyecto):

```bash
# En el host, desde la raíz del proyecto:
composer require google/apiclient

# O con Sail:
./vendor/bin/sail composer require google/apiclient
```

La dependencia ya está declarada en `composer.json` (`google/apiclient: ^2.18`). Si solo haces `composer install` / `composer update`, también se instalará una vez regenerado el lockfile.

Sin el paquete, el webhook responderá error al intentar crear eventos (`GoogleCalendarService`).
