# API (Chat + Contacto)

Este directorio deja funcionales los endpoints `POST /api/chat.php` y `POST /api/contact.php` para Hostinger compartido (PHP).

## Archivos
- `chat.php`: endpoint de chat (motor comercial v2)
- `contact.php`: endpoint de contacto (valida y guarda leads)
- `bootstrap.php`: helpers comunes (JSON, validacion, rate limit, HTTP)
- `assistant-v2/`: nuevo motor conversacional (LLM libre + extraccion + cierre comercial natural)
- `knowledge/`: base de conocimiento del negocio (servicios, FAQ, capacidades, proceso, CTA, objeciones)
- `.env` (crear manualmente): secretos y config privada
- `.env.example`: plantilla de variables
- `health.php`: healthcheck protegido por token
- `.user.ini`: ajustes PHP de produccion (`display_errors=0`)
- `.htaccess`: bloquea dotfiles y habilita rutas limpias opcionales

## Activacion
1. Copia `api/.env.example` a `api/.env`.
2. Define `DEEPSEEK_API_KEY` en `api/.env`.
3. (Opcional) Define `CONTACT_NOTIFY_EMAIL` para aviso por email.
4. Define `HEALTHCHECK_TOKEN` para habilitar `GET /api/health.php`.
5. Verifica permisos de escritura para `storage/`.
6. Sube `api/` y `storage/` a `public_html/`.

### Configuracion recomendada para Groq (pruebas)
- `DEEPSEEK_API_BASE=https://api.groq.com/openai/v1`
- `DEEPSEEK_MODEL=llama-3.1-8b-instant`
- `DEEPSEEK_API_KEY=<tu_groq_api_key>`

## Variables del agente
- `CHAT_HISTORY_MAX_ITEMS`: historial maximo recibido del frontend (default 10).
- `CHAT_MEMORY_MAX_TURNS`: cantidad de turnos guardados por sesion.
- `CHAT_V2_MEMORY_PATH`: memoria persistente especifica del motor v2.
- `CHAT_V2_READINESS_THRESHOLD`: umbral (0-100) para pasar a fase de cierre comercial.
- `CHAT_V2_CLOSE_SOFT_ATTEMPTS`: intentos suaves maximos para pedir contacto antes de CTA abierta.
- `CHAT_V2_TEMPERATURE`: temperatura del modelo para respuestas v2.
- `CHAT_V2_MAX_TOKENS`: limite de salida del modelo por turno en v2.
- `CHAT_KNOWLEDGE_PATH`: carpeta de conocimiento (`./knowledge`).
- `CHAT_RETRIEVAL_BLOCKS`: numero de bloques de contexto por respuesta.
- `CHAT_QUOTE_NOTIFY_EMAIL`: correo que recibe las solicitudes de cotizacion confirmadas.
- `CHAT_QUOTE_STORAGE_PATH`: ruta de respaldo para guardar cotizaciones si falla el envio por correo.

## Ajustes recomendados de seguridad
- `api/.htaccess` bloquea acceso a dotfiles (`.env`, `.user.ini`, etc).
- `api/.user.ini` deja `display_errors=0` en produccion.
- Usa `.htaccess` en raiz del proyecto para forzar `http -> https`.
- Valida que toda peticion final use `https://`.

## Endpoint de chat
- URL recomendada: `/api/chat.php`
- URL limpia opcional: `/api/chat`
- Metodo: `POST`
- Flujo v2 (por defecto):
  1. Chat libre desde el primer mensaje (sin gate obligatorio de nombre/correo).
  2. El LLM resuelve dudas y extrae datos comerciales en paralelo.
  3. Cuando el briefing esta maduro, el agente pide contacto de forma natural.
  4. Con nombre + correo validos y confirmacion explicita del usuario, se envia solicitud de cotizacion.
  5. Si falla el correo, la solicitud se guarda en almacenamiento de respaldo para seguimiento.
  6. Si no comparte contacto, no se bloquea el chat: se deja CTA abierta.
  7. Si piden fuera de alcance (ej. app movil nativa), el agente reconduce a alternativa web/PWA sin cortar la conversacion.
- Body JSON (ejemplo):

```json
{
  "sessionId": "sess_demo_123",
  "message": "Quiero una landing para captar leads",
  "context": {
    "source": "assistant-chat",
    "page": "assistant"
  },
  "history": [
    { "role": "user", "content": "hola" },
    { "role": "assistant", "content": "Hola, cuentame tu objetivo." }
  ]
}
```

- Respuesta esperada:

```json
{
  "reply": "Respuesta del agente",
  "sessionId": "sess_demo_123",
  "source": "api-v2",
  "lead": {
    "projectType": "web-corporativa",
    "requestedPlatform": "web",
    "stackPreference": "",
    "collaborationMode": "",
    "problem": "",
    "objective": "",
    "timeline": "",
    "contact": {
      "name": "",
      "email": "",
      "phone": "",
      "status": "not_requested"
    }
  },
  "readinessScore": 42,
  "nextBestAction": "clarify",
  "closeAttempt": "none",
  "canSendQuote": false,
  "scopeStatus": "supported",
  "leadStatus": "collecting-data",
  "missingFields": ["problem", "objective", "timeline"]
}
```

## Endpoint de contacto
- URL recomendada: `/api/contact.php`
- URL limpia opcional: `/api/contact`
- Metodo: `POST`
- Body JSON (ejemplo):

```json
{
  "name": "Ana Perez",
  "email": "ana@empresa.com",
  "company": "Empresa SA",
  "inquiryType": "producto",
  "message": "Necesito una landing para captacion de leads B2B",
  "source": "portfolio-web",
  "website": "",
  "formStartedAt": 1760000000000,
  "submittedAt": 1760000008000,
  "createdAt": "2026-03-02T10:00:00.000Z"
}
```

- Respuesta esperada:

```json
{
  "status": "ok",
  "id": "lead_xxxxxxxxxxxxxxxx",
  "stored": true,
  "notified": false
}
```

## Dato de almacenamiento
- Leads guardados en `storage/contacts.ndjson` (una linea JSON por contacto).
- Memoria de chat motor v2: `storage/chat-sessions-v2/<sessionId>.json`.

## Anti-spam aplicado
- Honeypot (`website`) para bots que rellenan todos los campos.
- Time-trap (`formStartedAt`/`submittedAt`) para bloquear envios demasiado rapidos.
- Rate limit por IP y limite diario por email.
- Rate limit en chat para evitar abuso de requests.
- En v2 no hay gate inicial de contacto: el cierre comercial se hace de forma progresiva.

## Regla comercial en chat
- No mostrar precios ni descuentos en el chat.
- Si preguntan precio, indicar que Andres enviara el detalle real cuando se confirme la cotizacion.

## Healthcheck
- URL: `/api/health.php?token=TU_TOKEN`
- Requiere `HEALTHCHECK_TOKEN` en `.env`.
- Valida HTTPS, `display_errors`, acceso a storage y presencia de `DEEPSEEK_API_KEY`.

## Verificacion automatizada en dominio
- Ejecuta:

```bash
node scripts/verify-production.mjs https://tu-dominio.com TU_HEALTHCHECK_TOKEN
```

- Alternativa con variable de entorno:

```bash
HEALTHCHECK_TOKEN=tu_token node scripts/verify-production.mjs https://tu-dominio.com
```

- El script revisa:
  - bloqueo de `api/.env`
  - guardas de metodo en `chat.php` y `contact.php`
  - ocultamiento de `X-Powered-By`
  - aliases `/api/chat` y `/api/contact`
  - redireccion `http -> https`
  - flujo real de POST en chat y contacto
