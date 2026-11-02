# Plan: Pipeline de Handoff Humano, Sesión y Búfer Anti-Metralleta

> Especificación: estado compartido `[instancia]:[chat]`, pausa por TTL, ciclo de vida de
> sesión con identificador único, búfer de acumulación y protección contra bucles (`mensajes_bot`).

## Decisiones confirmadas

1. **Integrar sobre el orquestador**: el pipeline nuevo gestiona pausa/sesión/búfer/fromMe y
   delega generación de IA al `AppointmentOrchestrator` existente (tools, prompts, guardas).
2. **MongoDB + Redis**: registro de conversaciones en Mongo (`whatsapp_sessions`) + puntero de
   sesión con TTL en Redis. `session_id` en `whatsapp_messages` delimita el contexto por sesión.
3. **Job en cola**: el webhook responde HTTP 200 inmediato y despacha `ProcessWhatsappMessage`.

## Modelo de datos y estado compartido

### Configuración por instancia (`WhatsappInstance`, Mongo)
- `tiempo_pausa_humano` (minutos, default `services.whatsapp.tiempo_pausa_humano` = 15).
- `tiempo_expiracion_sesion` (horas, default `services.whatsapp.tiempo_expiracion_sesion` = 24).
- Accesores `pauseMinutes()` / `sessionHours()` con fallback a config.

### Claves de memoria rápida (`ChatStateStore`)
| Clave | Contenido | TTL |
|---|---|---|
| `pausa:[instancia]:[chat]` | timestamp de control humano | `tiempo_pausa_humano` |
| `sesion:[instancia]:[chat]` | identificador de sesión activa | `tiempo_expiracion_sesion` |
| `buffer:[instancia]:[chat]` | lista de textos `{text, at}` | corto (limpieza al consumir) |
| `mensajes_bot:[id_mensaje]` | registro de envíos propios | 60 s |

`[instancia]` = `evolutionName()` (sesión Evolution, aísla instancias); `[chat]` = teléfono normalizado.

### BD (Mongo `whatsapp_sessions`) — modelo `WhatsappSession`
`company_id, instance_name, user_phone, session_id, status (activa|cerrada), close_reason
(inactividad|comando_cerrar|ia_despedida), opened_at, closed_at`.
`WhatsappMessage` gana `session_id` (fillable) para vincular cada mensaje a su conversación.

## Flujo del webhook (job `ProcessWhatsappMessage`)

1. **Descarte inmediato**: grupos/canales/estados (`@g.us|@broadcast|@newsletter`) → ignorados,
   HTTP 200. `fromMe` ya NO se descarta en el controller.
2. **Salientes (`fromMe: true`)**:
   - `key.id` en `mensajes_bot` → mensaje del bot → descartado (anti-bucle).
   - `#bot` → limpia `pausa:` y reactiva (`resumeBot` del estado de reservación).
   - `#cerrar` → cierra sesión (`comando_cerrar`) y borra puntero.
   - Cualquier otro → se guarda (role `assistant`, `metadata.source=operator`, con `session_id`)
     y se guarda/renueva `pausa:` con TTL de `tiempo_pausa_humano`.
3. **Entrantes (`fromMe: false`)**:
   - `pausa:` activa o `bot_paused_at` (escalamiento) → mensaje se guarda solo para
     auditoría (`metadata.source=audit`) y se detiene el flujo.
   - Si no: ciclo de sesión (`resolveOrCreate`: si falta el puntero, la conversación previa pasa
     a `Cerrada/inactividad` y se abre una nueva), mensaje guardado con `session_id`,
     luego se inserta en `buffer:` y se despacha `FlushChatBuffer`.
   - Transcripción de audio, media estática y welcome conservan su lógica actual.

## Búfer y generación de respuesta (job `FlushChatBuffer`)

- Ventana de espera `services.whatsapp.buffer_window_seconds` (default 8 s) medida desde el
  último mensaje del búfer: si llegan más mensajes la ventana se reinicia.
- Al quedar el búfer en reposo: se consolida todo en un solo bloque (`implode("\n")`).
- Contexto: `ConversationRepository::toLlmMessages(..., $sessionId)` filtra por `session_id`
  (solo la sesión activa; excluye sesiones previas). Incluye mensajes del cliente y los
  manuales del operador de esa misma sesión.
- Se llama a `AppointmentOrchestrator::respondToBuffered(...)` (sin doble debounce/pausa).
- Salida: `MessageDispatcher` registra cada `key.id` devuelto por Evolution en `mensajes_bot`
  (TTL 60 s) para evitar la autopausa.
- Etiqueta `[CONVERSATION_CLOSED]`: se elimina del texto antes de enviar y se ejecuta el cierre
  de sesión (`ia_despedida`).

## Archivos nuevos

- `app/Models/WhatsappSession.php`
- `app/Services/Whatsapp/ChatStateStore.php`
- `app/Services/Whatsapp/ChatSessionManager.php` (ciclo de vida: puntero TTL + registro Mongo)
- `app/Services/Whatsapp/WhatsappSessionRepository.php`
- `app/Jobs/ProcessWhatsappMessage.php`
- `app/Jobs/FlushChatBuffer.php`
- `tests/Feature/WhatsappHumanHandoffPipelineTest.php`
- `tests/Unit/ChatStateStoreAndDispatcherTest.php`

## Archivos modificados (diff mínimo)

- `app/Http/Controllers/Api/EvolutionWebhookController.php` → filtra, normaliza y despacha job.
- `app/Models/WhatsappInstance.php` → `tiempo_pausa_humano`, `tiempo_expiracion_sesion`.
- `app/Models/WhatsappMessage.php` → `session_id`.
- `app/Services/Whatsapp/ConversationRepository.php` → `session_id` opcional en
  `append/recent/toLlmMessages`.
- `app/Services/Whatsapp/AppointmentOrchestrator.php` → `respondToBuffered`, hilo de
  `sessionId`, consumo de `[CONVERSATION_CLOSED]`.
- `app/Services/Whatsapp/MessageDispatcher.php` → registro de ids en `mensajes_bot`.
- `config/services.php` → defaults de pausa, expiración, ventana de búfer y TTL de registro.

## Pruebas (TDD — primero fallidas)

1. Grupos/canales ignorados, 200 OK.
2. `fromMe` registrado en `mensajes_bot` → descartado (no pausa, no guarda).
3. `fromMe` manual → pausa con TTL + mensaje guardado con `session_id`.
4. `#bot` → limpia la pausa (reactiva de inmediato).
5. `#cerrar` → sesión `Cerrada` en Mongo + puntero eliminado.
6. Entrante con pausa activa → guardado para auditoría, sin llamada al orquestador.
7. Entrante crea sesión (Mongo + puntero TTL) y la renueva en cada mensaje.
8. Puntero expirado → sesión previa `Cerrada/inactividad` + nueva sesión.
9. Contexto del LLM acotado a la sesión activa (excluye sesiones previas).
10. Búfer: dos mensajes rápidos → UNA llamada al orquestador con el bloque consolidado;
    mientras la ventana siga viva no se despacha.
11. `[CONVERSATION_CLOSED]` se elimina del texto enviado y cierra la sesión.
12. Unit: claves/TTL del `ChatStateStore` y registro de ids de envío en `MessageDispatcher`.
