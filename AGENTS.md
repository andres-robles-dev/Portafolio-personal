# AGENTS.md

## Objetivo
- Guia operativa para agentes de codigo en este repositorio.
- Prioridad: plantilla personal de programador junior, contenido honesto y seguridad.
- La interfaz no es comercial: foto vacia, sin redes sociales ni proyectos inventados. Mantener la seccion Proyectos vacia y el acceso al agente conectado a la API. Solo el formulario de muestra valida localmente.
- No eliminar apartados ni reemplazar funcionalidades por demos locales sin peticion explicita. Si falta contenido, conservar la estructura vacia.
- Hacer cambios pequenos, trazables y compatibles con el contrato actual.

## Stack y alcance
- Frontend: HTML + CSS + JavaScript ESM (sin framework, sin bundler).
- Backend: PHP procedural en `api/`.
- Endpoints:
  - `POST /api/chat.php`
  - `POST /api/contact.php`
  - `GET /api/health.php` con `X-Health-Token`
- Motor activo: `api/assistant-v2/*`.

## Estructura clave
- Paginas: `index.html`, `asistente.html`.
- JS entrada:
  - `assets/js/main.js`
  - `assets/js/assistant.js`
  - `assets/js/static-nav.js`
- JS capas:
  - `assets/js/core/*`
  - `assets/js/modules/*`
  - `assets/js/services/*`
- API:
  - `api/bootstrap.php`
  - `api/chat.php`
  - `api/contact.php`
  - `api/health.php`
  - `api/assistant-v2/*`
  - `api/knowledge/motor.json`
- Persistencia:
  - `storage/contacts.ndjson`
  - `storage/chat-sessions-v2/*.json`
  - `storage/quotes.ndjson` (respaldo de cotizaciones)

## Cursor y Copilot
- No se detectaron reglas en:
  - `.cursorrules`
  - `.cursor/rules/`
  - `.github/copilot-instructions.md`
- Si aparecen nuevas reglas, actualizar este archivo en el mismo cambio.

## Comandos de desarrollo
- Local recomendado (frontend + API):
  - `php -S 127.0.0.1:8081 -t . router.php` (PowerShell: `$env:APP_MODE='demo'` para pruebas manuales sin efectos reales)
- Local solo estatico (sin API):
  - `node local-server.mjs`
  - `http://127.0.0.1:4173`

## Versionado y cache busting
- Este proyecto usa `release.json` para unificar version de despliegue.
- Antes de subir a Hostinger, ejecutar:
  - `node scripts/release-version.mjs bump`
- Para resincronizar HTML con la version actual:
  - `node scripts/release-version.mjs sync`
- Para validar consistencia local:
  - `node scripts/release-version.mjs verify`
- Regla de despliegue:
  - subir primero assets/API
  - subir `index.html` y `asistente.html` al final
  - luego validar version publicada

## Build, lint y test
- No hay build formal.
- No hay ESLint/Prettier/PhpUnit configurados.
- Pruebas aisladas: `node --test tests/*.test.mjs` y `php tests/security.php`.
- Prueba visual opcional: `node tests/interface.mjs` (Chrome/Chromium, o `CHROME_PATH`).
- No leer `api/.env`, consumir IA real, enviar correos ni escribir en el almacenamiento habitual durante pruebas; forzar `APP_LOAD_ENV=0` y rutas temporales.
- Validacion minima obligatoria:
  - sintaxis JS tocado
  - sintaxis PHP tocado
  - parseo JSON tocado
  - smoke test manual del flujo afectado

## Chequeos rapidos
- JS (single file):
  - `node --check assets/js/modules/assistantChat.js`
- PHP (single file):
  - `php -l api/assistant-v2/orchestrator.php`
- JSON (single file):
  - `node -e "JSON.parse(require('fs').readFileSync('api/knowledge/motor.json','utf8'))"`
- Release/version:
  - `node scripts/release-version.mjs verify`
- Hardcode guard:
  - `node scripts/block-hardcode.mjs`

## Single test commands (sin framework)
- Ejecutar los POST siguientes solo contra un servidor iniciado con `APP_MODE=demo`; en modo real tienen efectos.
- Chat:
  - `curl -X POST http://127.0.0.1:8080/api/chat.php -H "Content-Type: application/json" -d "{\"sessionId\":\"sess_smoke_1\",\"message\":\"Quiero una landing para captar leads\",\"context\":{\"source\":\"qa\",\"page\":\"assistant\"},\"history\":[]}"`
- Contacto:
  - `curl -X POST http://127.0.0.1:8080/api/contact.php -H "Content-Type: application/json" -d "{\"name\":\"QA Test\",\"email\":\"qa@example.com\",\"company\":\"Test\",\"inquiryType\":\"producto\",\"message\":\"Necesito una landing para captar leads B2B con objetivo comercial.\",\"source\":\"qa\",\"formStartedAt\":1760000000000,\"submittedAt\":1760000008000,\"createdAt\":\"2026-03-01T10:00:00.000Z\"}"`
- Health produccion:
  - `node scripts/verify-production.mjs https://tu-dominio.com` (token solo en entorno)
- Cache/version en produccion:
  - `node scripts/verify-production.mjs https://tu-dominio.com` (lectura por defecto; `--exercise` autoriza POST con efectos)
  - validar que reporta `Version unificada entre index y asistente` en `OK`

## Contrato chat (frontend)
- Campos esperados:
  - `reply`, `sessionId`, `source`, `suggestions`
  - `lead`, `missingFields`, `readinessScore`
  - `nextBestAction`, `closeAttempt`, `canSendQuote`
  - `leadStage`, `leadStatus`, `scopeStatus`
  - `quoteSendResult`, `quoteSendReason`, `retryAfter`

## Convenciones de codigo
- Indentacion: 4 espacios.
- JS ESM con exports nombrados.
- `const` por defecto, `let` solo cuando haya reasignacion.
- Preferir funciones pequenas y retornos tempranos.
- Evitar duplicacion de logica.
- Evitar comentarios obvios.

## Imports, formato y nombres
- Separar responsabilidades por capa (`core`, `modules`, `services`).
- No mezclar UI con llamadas de red.
- Nombres de funciones orientados a accion (`init*`, `resolve*`, `request*`).
- JS: `camelCase`; constantes globales: `UPPER_SNAKE_CASE`.
- PHP: `snake_case` con prefijos de dominio (`api_*`, `agent_v2_*`).

## Tipos y validacion
- No hay TypeScript, validar shapes en runtime.
- Normalizar payloads externos antes de usarlos.
- En PHP, sanitizar y truncar input con helpers existentes.
- Mantener esquema de knowledge JSON:
  - `document`, `version`, `priority`, `blocks[]`

## Manejo de errores y resiliencia
- Backend:
  - responder siempre JSON
  - usar codigos semanticos (`RATE_LIMIT`, `VALIDATION_ERROR`, etc)
  - no exponer detalles sensibles
- Frontend:
  - `fetch` con `AbortController`
  - degradacion sin romper UX
  - feedback claro al usuario

## Reglas del agente conectado a la API
- Responder en espanol, tono profesional y directo.
- No prometer resultados garantizados.
- No dar precios cerrados en chat.
- Maximo una pregunta de avance por turno.
- No forzar el cierre; guiar con naturalidad.
- Si piden app movil nativa, reencuadrar a alternativa web/PWA por fases.
- Si no hay evidencia suficiente, responder con transparencia y hacer una sola pregunta clave.
- Cualquier respuesta final del LLM debe pasar por `policy-guard` antes de salir al frontend.

## Seguridad y configuracion
- Nunca commitear `api/.env`.
- Nunca hardcodear emails reales, telefonos o secretos en codigo/documentacion del repo.
- Mantener `.htaccess` en raiz, `api/`, `api/assistant-v2/`, `api/knowledge/` y `storage/`.
- Produccion con HTTPS.
- Revisar permisos de `storage/` antes de desplegar.
- No servir el proyecto con un servidor estatico generico ni PHP sin `router.php`.
- `APP_MODE=demo` no utiliza IA externa ni conserva contactos/conversaciones; el modo real requiere configuracion explicita en instalaciones nuevas.
- `sessionId` es de compatibilidad: la propiedad se deriva de una cookie aleatoria HttpOnly, no del JSON del cliente.
- No reanudar memoria caducada ni borrar datos historicos automaticamente; limpieza explicita con `scripts/cleanup-memory.php --apply`.
- No guardar ni reenviar contactos automaticamente desde localStorage; borrar borradores antiguos solo a peticion del usuario.
- No confiar en X-Forwarded-For salvo proxies incluidos expresamente en TRUSTED_PROXIES.

## Flujo de cierre (critico)
- Para enviar cotizacion:
  - contacto valido (nombre + email)
  - briefing minimo util
  - confirmacion explicita
- Si falla correo:
  - no perder lead
  - guardar respaldo en storage
  - informar alternativa de contacto

## Verificacion manual minima
- Home:
  - navegacion
  - transiciones
  - modal de proyectos
  - formulario contacto
- Asistente:
  - respuesta normal
  - sugerencias y estado
  - cierre con confirmacion
  - fallback cuando falla correo

## Regla de mantenimiento
- Si cambias contratos o reglas comerciales:
  - actualiza `AGENTS.md` y `api/README.md` en el mismo cambio.
- No reintroducir motor legacy ni rutas fuera de `assistant-v2`.
- Mantener una sola fuente de reglas de negocio en `api/knowledge/motor.json`.
