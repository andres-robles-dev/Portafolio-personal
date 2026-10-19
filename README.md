# Portafolio personal — práctica de programación

Web personal de **Andrés Robles**, desarrollada para practicar interfaces con HTML, CSS y JavaScript modular, y una API con PHP. Se presenta como proyecto de aprendizaje: no es un producto terminado ni una garantía de resultados comerciales.

## Qué incluye

- Portada, navegación, transiciones y secciones de proyectos.
- Modales con información de cada proyecto.
- Formulario de contacto con validación.
- Asistente experimental con contexto de negocio, extracción de datos y confirmación antes de solicitar una cotización.
- **Modo demo** para probar la interfaz sin IA externa, correos ni almacenamiento de contactos o conversaciones.

**Tecnologías:** HTML, CSS, JavaScript ESM y PHP 8.1+. No hay framework, compilación, npm ni base de datos. Node.js 22+ se utiliza para la vista estática y las pruebas. El modo real de IA requiere PHP cURL.

## Ejecutar en local

Desde la carpeta del proyecto, en PowerShell:

```powershell
$env:APP_MODE = 'demo'
php -S 127.0.0.1:8081 -t . router.php
```

Abre **http://127.0.0.1:8081**. El asistente está en `/asistente.html`. Detén el servidor con **Ctrl+C**. Puedes elegir otro puerto si está ocupado.

En Linux/macOS:

```bash
APP_MODE=demo php -S 127.0.0.1:8081 -t . router.php
```

Usa siempre `router.php`: el servidor integrado de PHP **no aplica `.htaccess`**. Este router bloquea secretos, Git, registros y código interno. Se incluye también un servidor estático protegido:

```bash
node local-server.mjs
```

Abre `http://127.0.0.1:4173`. Esta alternativa sirve **solo el frontend**: no ejecuta PHP ni el asistente/contacto de la API. No abras directamente `index.html` con `file://`, porque utiliza módulos JavaScript.

## Configuración opcional del modo real

No hace falta una clave para explorar el proyecto en modo demo. Para activar la integración real:

1. Si todavía no existe `api/.env`, crea una copia de `api/.env.example`. **No sobrescribas una configuración existente.**
2. Configura `APP_MODE=live`, el proveedor/modelo y su clave exclusivamente en el entorno del servidor o en ese archivo privado.
3. Configura almacenamiento privado, límites y, solo si lo necesitas, destinatarios de correo.
4. Reinicia PHP y utiliza únicamente datos ficticios para pruebas.

La plantilla empieza en `demo`. Por compatibilidad, una instalación existente sin `APP_MODE` conserva `live`; la variable del proceso tiene prioridad sobre el archivo. Si estableciste `APP_MODE` en PowerShell, recuerda retirarla al terminar con `Remove-Item Env:APP_MODE`.

Nunca publiques `api/.env`, contactos, conversaciones, cotizaciones ni copias de esos archivos. Más información en [la documentación de la API](api/README.md) y [seguridad](SECURITY.md).

## Organización

```text
index.html / asistente.html    Páginas
assets/css/                   Estilos
assets/js/core/               Estado y configuración pública
assets/js/modules/            Interfaz e interacciones
assets/js/services/           Acceso a la API
api/                          Endpoints PHP y configuración privada
api/assistant-v2/             Motor activo del asistente
api/knowledge/motor.json       Única fuente de reglas de negocio
storage/                      Datos locales, bloqueados por HTTP e ignorados por Git
router.php                    Router seguro de desarrollo
scripts/                      Validación, mantenimiento y versionado
tests/                        Pruebas con datos ficticios y almacenamiento temporal
```

## Comprobaciones

```bash
node --test tests/*.test.mjs
php tests/security.php
node scripts/block-hardcode.mjs --history
node scripts/release-version.mjs verify
```

Las pruebas aíslan la configuración real con `APP_LOAD_ENV=0`, utilizan proveedores simulados y no envían correos ni consumen una clave real. Comprueban rutas privadas, validación, sesiones, límites concurrentes, almacenamiento aislado y servicios del frontend. La comprobación visual se puede repetir con `node tests/interface.mjs` si tienes Chrome/Chromium instalado; admite `CHROME_PATH`.

## Lo que practiqué

JavaScript organizado por módulos, manipulación segura del DOM, diseño adaptable, peticiones asíncronas, validación en cliente y servidor, gestión de estado, cookies de sesión, integración con APIs y separación de configuración privada.

## Limitaciones y mejoras pendientes

- El asistente y su filtro de políticas son experimentales: pueden equivocarse. No se promete eliminar alucinaciones ni ofrecer seguridad de producción.
- El modo demo no genera respuestas reales de IA ni envía solicitudes.
- Los recursos de Google Fonts necesitan conexión; no es una PWA offline.
- Una demo pública con IA real necesita controles adicionales de acceso, cuotas del proveedor y una política de privacidad/retención adecuada.
- Quedan por ampliar las pruebas de accesibilidad, dispositivos y entrega real de correo.
- Antes de hacer público el repositorio, revisar derechos de imágenes y recursos y decidir la licencia. Esta revisión no añade una licencia ni cambia su visibilidad.
