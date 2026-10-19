# API de contacto y asistente

Backend PHP 8.1+ para el proyecto de aprendizaje. Motor activo: `assistant-v2`; las reglas comerciales viven en `knowledge/motor.json`. No requiere base de datos. La IA real requiere cURL.

El agente de `asistente.html` consume esta API. El formulario de muestra de la portada valida localmente y no envía solicitudes. No hace falta activar el proveedor IA para ver la portada, pero sí para obtener respuestas reales del agente.

## Arranque

Desde la raíz: `php -S 127.0.0.1:8081 -t . router.php`. No utilices el servidor PHP sin router ni sirvas esta carpeta con un servidor estático genérico. El servidor Node incluido bloquea la API y sirve solo frontend.

Si no existe tu archivo privado, copia `.env.example` como `.env`, sin sobrescribir una configuración existente. La plantilla usa `APP_MODE=demo`: el asistente responde con una demostración identificada, no llama a IA ni conserva conversaciones, y contacto no guarda ni envía solicitudes.

`APP_MODE=live` habilita el flujo real. Por compatibilidad, si la variable no existe se conserva el comportamiento real de las instalaciones previas. Las variables del proceso prevalecen sobre `.env`. `APP_LOAD_ENV=0` impide cargar el archivo privado y se utiliza para aislar las pruebas; no debe sustituir la configuración real de un despliegue.

## Configuración

| Variable | Uso |
|---|---|
| `APP_MODE` | `demo` o `live` |
| `API_ENV_FILE` | Ruta opcional de un archivo de entorno privado fuera de la raíz pública |
| `DEEPSEEK_API_KEY` | Clave privada del proveedor compatible; el nombre histórico se conserva |
| `DEEPSEEK_API_BASE` | Base HTTPS; por defecto Groq OpenAI-compatible |
| `DEEPSEEK_MODEL` | Modelo permitido por tu cuenta; por defecto `llama-3.1-8b-instant` |
| `API_CA_FILE` | Archivo PEM de autoridades fiable, opcional |
| `CHAT_GLOBAL_DAILY_MAX` | Límite global diario de mensajes, por defecto 200 |
| `CONTACT_GLOBAL_HOURLY_MAX` | Límite global de contacto por hora, por defecto 100 |
| `TRUSTED_PROXIES` | IPs exactas de proxies de confianza, separadas por coma; vacío por defecto |
| `RATE_LIMIT_DIR` | Directorio de contadores fuera de la raíz pública; por defecto en temporales del sistema |
| `CHAT_SESSION_TTL_SECONDS` | Caducidad de memoria inactiva; por defecto siete días |
| `CHAT_V2_MEMORY_PATH` | Directorio de memoria por sesión |
| `CONTACT_STORAGE_PATH` / `CHAT_QUOTE_STORAGE_PATH` | Archivos privados de contactos y cotizaciones |
| `CONTACT_NOTIFY_EMAIL` / `CHAT_QUOTE_NOTIFY_EMAIL` | Destinatarios privados opcionales |
| `PUBLIC_CONTACT_EMAIL` / `PUBLIC_CONTACT_WHATSAPP` | Canales opcionales de fallback, nunca hardcodeados |
| `HEALTHCHECK_TOKEN` | Secreto del healthcheck, enviado como cabecera |

La plantilla conserva las demás variables de historial, readiness, tono, tokens, rate limit y anti-spam. La salida de IA se acota a 1.200 tokens y TLS siempre se verifica. Windows utiliza confianza nativa de cURL si está disponible. Las rutas absolutas de almacenamiento funcionan también en Windows.

## Endpoints

### `POST /api/chat.php` (alias `/api/chat`)

Content-Type: `application/json`; cuerpo máximo de 64 KiB.

```json
{
  "message": "Ejemplo ficticio: quiero practicar una landing",
  "context": { "source": "demo", "page": "assistant" },
  "history": []
}
```

`sessionId` sigue siendo aceptado por compatibilidad, pero no determina qué memoria se carga. El servidor vincula la sesión a una cookie de propiedad `HttpOnly` y devuelve un ID derivado. Los clientes que necesiten continuidad deben conservar esa cookie; el navegador lo hace con `credentials: same-origin`. Copiar el ID de otro cliente no permite acceder a su memoria.

Se conservan `reply`, `sessionId`, `source`, `suggestions`, `lead`, `missingFields`, `readinessScore`, `nextBestAction`, `closeAttempt`, `canSendQuote`, `leadStage`, `leadStatus`, `scopeStatus` y, cuando corresponde, `quoteSendResult`, `quoteSendReason`, `retryAfter`. `source=demo` identifica una respuesta sin IA externa.

En modo real, el LLM recibe el mensaje, parte del historial, contexto de negocio y datos comerciales disponibles. La respuesta pasa por normalización y `policy-guard`, que es un filtro heurístico, no una garantía contra errores. El cierre requiere briefing útil, contacto válido y confirmación después del estado de cierre final. Si falla el correo pero se guarda la cotización, se informa como pendiente de seguimiento.

### `POST /api/contact.php` (alias `/api/contact`)

Campos: `name`, `email`, `company` opcional, `inquiryType`, `message`, `source` opcional y los filtros auxiliares `website`, `formStartedAt`, `submittedAt`. Se usan tipos y tamaños limitados; la fecha almacenada la fija el servidor.

- Modo demo: `{"status":"demo","stored":false,"notified":false}`.
- Aceptación real: `status=ok`, `stored=true`, con `notified` indicando si `mail()` aceptó el aviso.
- Un 200 con `stored=false` no acredita recepción real. El frontend comprueba el cuerpo y no guarda ni reintenta borradores en `localStorage`.

### `GET /api/health.php` (alias `/api/health`)

Requiere `X-Health-Token` con el valor configurado. No acepta tokens en la URL, no devuelve rutas internas y no escribe archivos. Comprueba configuración y permisos de forma no destructiva. Bajo HTTP local puede devolver 503 porque no hay HTTPS; eso no impide probar la demo.

## Pruebas y despliegue

```bash
node --test tests/*.test.mjs
php tests/security.php
node scripts/block-hardcode.mjs --history
node scripts/release-version.mjs verify
```

Las pruebas fuerzan `APP_LOAD_ENV=0`, usan proveedor simulado y almacenamiento temporal. No consumen claves reales ni envían correo. La prueba visual opcional es `node tests/interface.mjs`.

`node scripts/verify-production.mjs https://tu-dominio.com` hace comprobaciones de lectura. El token se toma solo de `HEALTHCHECK_TOKEN` en el entorno. `--exercise` autoriza POST que pueden consumir cuota, guardar solicitudes o enviar correo en modo real: no lo uses contra producción sin entender esos efectos.

Apache necesita los `.htaccess` del proyecto y los subdirectorios. Nginx requiere restricciones equivalentes. Antes de un despliegue real, revisa cuotas del proveedor, acceso, permisos, privacidad, retención de contactos/cotizaciones y derechos de los recursos. GitHub Pages sirve HTML, pero no este backend PHP.
