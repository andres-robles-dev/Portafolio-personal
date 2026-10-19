# Seguridad y privacidad

Este es un proyecto de aprendizaje. Las comprobaciones incluidas reducen riesgos conocidos, pero no equivalen a una auditoría de producción.

El formulario de muestra solo valida localmente. El agente sí utiliza el backend: en modo real envía los mensajes al proveedor de IA configurado. Las pruebas utilizan un servidor aislado en modo demo y respuestas simuladas, no credenciales reales.

## Secretos y archivos privados

- Las claves solo van en el entorno del servidor o en `api/.env`, nunca en JavaScript, documentación, incidencias o capturas.
- No publiques `.env`, `.git`, registros, contactos ni conversaciones. `.gitignore` no limpia secretos ya incluidos en commits anteriores.
- El router PHP y el servidor Node permiten únicamente recursos públicos. `.htaccess` protege Apache; Nginx necesita reglas equivalentes.
- En un alojamiento real, es preferible situar configuración y datos fuera de la raíz pública y configurar sus rutas. `API_ENV_FILE` permite señalar un archivo de entorno exterior. Conserva las restricciones de HTTP incluso si los mueves.
- En Linux, utiliza permisos restrictivos para datos y contadores (directorios `700`, archivos `600`, propietario apropiado para PHP). En Windows revisa los permisos NTFS. No uses permisos globales de escritura.

## Sesiones del asistente

El servidor emite una cookie aleatoria `HttpOnly`, `SameSite=Strict`, con `Secure` bajo HTTPS. El ID que devuelve la API deriva de esa cookie: copiar `sessionId` en un JSON no permite reanudar la sesión de otro navegador. El identificador ya no se persiste en `localStorage`. Las sesiones antiguas basadas solo en un ID del cliente no se migran ni se borran automáticamente.

La memoria deja de reanudarse tras siete días sin cambios por defecto (`CHAT_SESSION_TTL_SECONDS`). `node` no tiene acceso a esa memoria por HTTP. Para revisar archivos caducados, utiliza `php scripts/cleanup-memory.php`; solo borra con `--apply`. Revisa el resultado y tus copias antes de hacerlo. Los contactos y cotizaciones reales requieren una política de retención y un proceso de eliminación propios; no se borran automáticamente en esta revisión.

## Límites y entradas

- Se valida método, origen declarado, Content-Type, tamaño de cuerpo JSON y tipos de campos.
- La IP procede de `REMOTE_ADDR`. Solo se usa `X-Forwarded-For` cuando el proxy directo está expresamente en `TRUSTED_PROXIES`.
- El contador mantiene un bloqueo durante toda la lectura/modificación/escritura. Si no puede usarlo, falla de forma controlada.
- Hay límites por IP y globales: por defecto, 200 mensajes de chat al día y 100 solicitudes de contacto a la hora para toda la instalación.
- Los contadores se guardan fuera de la raíz pública y no incluyen mensajes. Sus claves son hashes, no anonimización garantizada.
- Honeypot y tiempos del formulario son filtros complementarios, no pruebas de identidad.
- Estas medidas no autentican a un bot. Antes de abrir una API de IA pública, configura límites de gasto/cuota en el proveedor y protección adicional del alojamiento. No es un proxy público de uso general ni un sistema distribuido de cuotas.

## IA, correo y pruebas

La verificación TLS permanece habilitada. En Windows se usa el almacén nativo de certificados cuando cURL lo admite; puedes configurar `API_CA_FILE` con un archivo PEM fiable. No desactives la verificación para resolver errores de conexión.

Las respuestas del proveedor se presentan como texto, no como HTML ejecutable. El filtro de políticas es heurístico y no garantiza precisión. Para enviar una cotización se exige contacto válido, briefing útil y confirmación en el estado apropiado; un bloqueo por sesión reduce duplicados concurrentes. `mail()` solo confirma aceptación local, no entrega al destinatario.

Las pruebas automatizadas no deben leer `api/.env`, contactar con una IA real, enviar correo ni escribir datos en el almacenamiento habitual. El modo demo no hace ninguna de esas operaciones. No utilices datos personales reales al demostrar el proyecto.

El formulario ya no guarda borradores nuevos ni reenvía los antiguos automáticamente. Si quedan borradores de versiones anteriores, la interfaz ofrece borrarlos por decisión del usuario. El modo real conserva solicitudes en el servidor y envía contenido a un proveedor de IA: requiere información de privacidad y configuración adecuada antes de aceptar usuarios reales.

## Publicación

Ejecuta `node scripts/block-hardcode.mjs --history` y revisa los archivos preparados antes de cada publicación. El detector muestra rutas/categorías, no los valores sospechosos; no detecta todas las formas posibles de un secreto. Si se expuso uno, revócalo y revisa el historial antes de hacer público el repositorio. No cambies la visibilidad ni reescribas el historial automáticamente.

El healthcheck utiliza `X-Health-Token`, no un token en la URL, y no revela rutas internas ni hace escrituras de prueba. `verify-production.mjs` es de solo lectura por defecto; `--exercise` autoriza peticiones que pueden tener efectos reales, por lo que debe usarse únicamente de forma consciente.
