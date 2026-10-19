# Andrés Robles — plantilla personal junior

Plantilla de portafolio creada como **práctica de programación**. Presenta mi perfil de programador junior y las tecnologías con las que sigo aprendiendo, sin vender servicios ni mostrar trabajos o experiencia profesional inventados.

## Contenido

- Portada con el espacio de la fotografía vacío, sin imagen ni elemento sustituto.
- Presentación personal y forma de aprender.
- Mi stack de frontend, backend y bases de datos.
- Formulario de muestra: valida en el navegador, **no envía ni guarda datos**.
- Un pequeño ejercicio con canvas, Duck Runner.
- Apartado Proyectos y su navegación, con la estructura vacía para que el autor añada su propio contenido.
- Agente accesible desde el menú en `asistente.html`, conectado a la API PHP y al proveedor configurado.

No se inventan productos, proyectos ni trabajos para clientes. Las redes sociales están eliminadas. Es una plantilla de estudio, pero se mantienen sus apartados y el funcionamiento del agente.

## Mi stack

| Área | Tecnologías |
|---|---|
| Frontend | HTML, CSS, JavaScript, Bootstrap, React y Tailwind CSS |
| Backend | Java, PHP, Spring Boot, Spring Security y APIs REST |
| Bases de datos | SQL, PostgreSQL y MySQL |

Este es mi stack personal, **no una lista de dependencias de esta plantilla**. La interfaz de este repositorio está hecha con HTML, CSS y JavaScript ESM, sin framework ni compilación. El agente utiliza la API PHP; el formulario de muestra permanece local.

## Ver en local

Con Node.js 22 o superior, desde la carpeta del proyecto:

```bash
node local-server.mjs
```

Abre **http://127.0.0.1:4173** para ver solo la interfaz. Este servidor no ejecuta la API del agente. Detén el servidor con **Ctrl+C**. No abras `index.html` mediante `file://`, porque utiliza módulos JavaScript.

Para utilizar también el agente, ejecuta PHP 8.1 o superior:

```powershell
$env:APP_MODE = 'live'
php -S 127.0.0.1:8081 -t . router.php
```

Abre **http://127.0.0.1:8081**. El agente necesita una clave válida en la configuración privada y cURL habilitado. No sobrescribas una configuración existente ni publiques sus secretos. Para pruebas sin IA externa puedes establecer `APP_MODE=demo`: es una opción del servidor, no una respuesta fija impuesta en el navegador. Usa siempre el router incluido: el servidor integrado de PHP no aplica `.htaccess`.

## Organización y pruebas

`index.html` contiene la plantilla, `assets/css/` sus estilos y `assets/js/` la navegación, validación e interacciones. `api/` contiene el backend del agente, documentado en [api/README.md](api/README.md). No necesitas activarlo si solo quieres ver la portada.

```bash
node --test tests/*.test.mjs
php tests/security.php
node tests/interface.mjs
node scripts/block-hardcode.mjs --history
node scripts/release-version.mjs verify
```

La prueba visual necesita Chrome/Chromium; admite `CHROME_PATH`. Las pruebas del backend usan configuración y almacenamiento aislados, sin credenciales reales ni correos. Consulta [SECURITY.md](SECURITY.md) si deseas estudiar o activar ese código por separado.

## Alcance

El objetivo es practicar estructura, diseño adaptable, DOM, módulos, formularios y organización del código, no presentar una empresa ni un producto comercial. Los recursos de Google Fonts necesitan conexión. La licencia de distribución queda pendiente de elección del autor; el repositorio no cambia de visibilidad automáticamente.
