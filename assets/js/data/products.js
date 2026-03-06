export const products = [
    {
        slug: "landing-b2b",
        name: "Landing de captacion B2B",
        kicker: "Producto 01",
        subtitle: "Pagina orientada a captar leads y convertir visitas en oportunidades reales.",
        status: "Disponible para implementar",
        timeline: "10-14 dias habiles",
        realDemoUrl: "",
        summary: [
            "Ideal para negocios que necesitan generar contactos comerciales rapido con una propuesta de valor clara y una experiencia enfocada en accion.",
            "Incluye estructura de conversion, bloques de confianza, CTA visibles y formulario pensado para reducir friccion en desktop y mobile.",
            "Se entrega lista para publicar, medir resultados y evolucionar en nuevas iteraciones sin rehacer desde cero."
        ],
        tags: ["Leads", "Conversion", "B2B", "Ventas"],
        deliverables: [
            "Landing de conversion enfocada en objetivo",
            "Copy base y estructura de confianza",
            "Formulario de contacto o captacion",
            "Version responsive con performance base"
        ],
        gallery: [
            { src: "./img/Proyecto nuevo (7).png", alt: "Dashboard de conversion y analitica comercial" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Equipo de marketing revisando resultados" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Diseno de landing page orientado a conversion" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Panel CRM con seguimiento de leads B2B" }
        ]
    },
    {
        slug: "corporate-sales-site",
        name: "Web corporativa multipagina",
        kicker: "Producto 02",
        subtitle: "Sitio web para presentar marca, servicios y casos con claridad.",
        status: "Disponible para implementar",
        timeline: "14-24 dias habiles",
        realDemoUrl: "",
        summary: [
            "Pensada para empresas que necesitan una presencia digital profesional para reforzar credibilidad y facilitar conversaciones comerciales.",
            "Organiza la informacion con una narrativa clara de marca, servicios, casos y llamadas a la accion en puntos clave del recorrido.",
            "Queda preparada para crecer con nuevas secciones, contenidos o integraciones segun evolucione el negocio."
        ],
        tags: ["Marca", "Confianza", "Ventas", "Web"],
        deliverables: [
            "Home y paginas clave del sitio",
            "Arquitectura de contenido por objetivos",
            "Seccion de casos o prueba social",
            "Rutas de contacto y conversion"
        ],
        gallery: [
            { src: "./img/Proyecto nuevo (7).png", alt: "Diseno de sitio corporativo en pantallas grandes" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Espacio creativo para branding corporativo" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Prototipo UI de web corporativa" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Equipo comercial y de producto en reunion" }
        ]
    },
    {
        slug: "internal-ops-panel",
        name: "Sistema interno de flujos de trabajo",
        kicker: "Producto 03",
        subtitle: "Aplicacion interna para ordenar tareas, datos y procesos del equipo.",
        status: "Disponible para implementar",
        timeline: "18-28 dias habiles",
        realDemoUrl: "",
        summary: [
            "Resuelve problemas de operacion cuando el equipo trabaja con procesos dispersos, tareas manuales o informacion repartida.",
            "Incluye autenticacion, panel principal y modulos CRUD para centralizar flujo operativo en una base ordenada.",
            "Se disena para escalar por fases con reportes, roles avanzados e integraciones segun crecimiento del negocio."
        ],
        tags: ["Operaciones", "Flujos", "MySQL", "Eficiencia"],
        deliverables: [
            "Login y control de acceso",
            "Panel central de operacion",
            "Modulos CRUD iniciales",
            "Base lista para escalar"
        ],
        gallery: [
            { src: "./img/Proyecto nuevo (7).png", alt: "Dashboard operativo con modulos de gestion" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Desarrollo de software para panel interno" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Pantalla con reportes de operaciones" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Flujo de trabajo digital en entorno de oficina" }
        ]
    },
    {
        slug: "simple-business-api",
        name: "API sencilla para integraciones",
        kicker: "Producto 04",
        subtitle: "Backend ligero para conectar formularios, sistemas y herramientas externas.",
        status: "Disponible para implementar",
        timeline: "7-15 dias habiles",
        realDemoUrl: "",
        summary: [
            "Pensada para negocios que necesitan exponer o consumir datos entre web, CRM, formularios o herramientas de automatizacion.",
            "Incluye endpoints claros, validaciones basicas y estructura mantenible para evolucionar sin complejidad innecesaria.",
            "Se entrega documentada y preparada para sumar autenticacion, reglas de negocio y nuevas integraciones por etapas."
        ],
        tags: ["API", "Integraciones", "Backend", "Node"],
        deliverables: [
            "Endpoints REST para casos concretos",
            "Validaciones y estructura base",
            "Conexion con frontend o servicio externo",
            "Documentacion inicial de uso"
        ],
        gallery: [
            { src: "./img/Proyecto nuevo (7).png", alt: "Diagrama de endpoints para API de negocio" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Integracion entre formulario web y backend" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Respuesta JSON en pruebas de API" },
            { src: "./img/Proyecto nuevo (7).png", alt: "Panel de monitoreo para servicios conectados" }
        ]
    }
];

export function getProductBySlug(slug) {
    return products.find((product) => product.slug === slug);
}
