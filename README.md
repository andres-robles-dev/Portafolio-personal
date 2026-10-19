# Andrés Robles — plantilla personal junior

Plantilla de portafolio creada como **práctica de programación**. Presenta mi perfil de programador junior y las tecnologías con las que sigo aprendiendo, sin vender servicios ni mostrar trabajos o experiencia profesional inventados.

## Contenido

- Portada sin fotografía, con una tarjeta visual de código.
- Presentación personal y forma de aprender.
- Mi stack de frontend, backend y bases de datos.
- Formulario de muestra: valida en el navegador, **no envía ni guarda datos**.
- Un pequeño ejercicio con canvas, Duck Runner.
- Una interfaz de chat opcional en `asistente.html`, con respuestas locales de ejemplo, sin IA ni peticiones al servidor.

No incluye catálogo, sección de proyectos, redes sociales ni cotizaciones en la interfaz. Es una plantilla de estudio: no necesita que todos los componentes representen funcionalidades de un producto real.

## Mi stack

| Área | Tecnologías |
|---|---|
| Frontend | HTML, CSS, JavaScript, Bootstrap, React y Tailwind CSS |
| Backend | Java, PHP, Spring Boot, Spring Security y APIs REST |
| Bases de datos | SQL, PostgreSQL y MySQL |

Este es mi stack personal, **no una lista de dependencias de esta plantilla**. La interfaz de este repositorio está hecha con HTML, CSS y JavaScript ESM, sin framework ni compilación. El ejemplo PHP de la práctica anterior se conserva aparte, pero no está conectado al formulario ni al chat de muestra.

## Ver en local

Con Node.js 22 o superior, desde la carpeta del proyecto:

```bash
node local-server.mjs
```

Abre **http://127.0.0.1:4173**. No necesitas configurar claves, correo o base de datos. Detén el servidor con **Ctrl+C**. No abras `index.html` mediante `file://`, porque utiliza módulos JavaScript.

Si prefieres PHP 8.1 o superior:

```powershell
$env:APP_MODE = 'demo'
php -S 127.0.0.1:8081 -t . router.php
```

Abre **http://127.0.0.1:8081**. Usa siempre el router incluido: el servidor integrado de PHP no aplica `.htaccess`.

## Organización y pruebas

`index.html` contiene la plantilla, `assets/css/` sus estilos y `assets/js/` la navegación, validación e interacciones. `api/` conserva un backend PHP de ejemplo independiente, documentado en [api/README.md](api/README.md). No es necesario activarlo para utilizar la plantilla.

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
