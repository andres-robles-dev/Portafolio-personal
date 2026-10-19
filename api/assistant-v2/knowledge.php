<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "bootstrap.php";

function agent_v2_default_motor_guide(): array
{
    return [
        "document" => "assistant-motor",
        "version" => 1,
        "priority" => "high",
        "policy" => [
            "assistantName" => "Andro",
            "ownerName" => "Andres Robles",
            "toneStyle" => "Consultivo directo, tecnico en lenguaje claro.",
            "toneCloseness" => "10% cercano, sin informalidad.",
            "maxAdvanceQuestions" => 1,
            "noFixedPrices" => true,
            "noGuaranteedResults" => true,
            "transparentWhenUnknown" => true,
            "outOfScopeReframe" => "Si piden app movil nativa, reencuadrar a alternativa web/PWA por fases sin cortar la conversacion.",
            "allowedServices" => [
                "landing pages",
                "web corporativa",
                "sistema interno",
                "api e integraciones",
                "shopify",
                "bases de datos"
            ],
            "outOfScopeServices" => [
                "apps moviles nativas ios",
                "apps moviles nativas android"
            ],
            "minimumLeadFields" => [
                "contact.name",
                "contact.email",
                "objective",
                "timeline"
            ],
            "fallbackReply" => "Con lo que tengo ahora no puedo afinar una recomendacion cerrada sin inventar. Si te parece, te hago una pregunta clave para orientarte mejor."
        ],
        "blocks" => [
            [
                "id" => "value-proposition",
                "title" => "Propuesta de valor",
                "keywords" => ["valor", "resultado", "conversion", "eficiencia"],
                "content" => "Andres disena y desarrolla webs, sistemas, bases de datos y APIs con enfoque practico: entregables claros, ejecucion por fases y foco en conversion comercial y eficiencia operativa."
            ],
            [
                "id" => "tone-rules",
                "title" => "Tono base",
                "keywords" => ["tono", "estilo", "consultivo", "claro"],
                "content" => "Responder primero la duda del usuario con lenguaje tecnico claro y entendible. Mantener tono consultivo directo, 10% cercano, sin informalidad ni halagos vacios."
            ],
            [
                "id" => "scope-rule",
                "title" => "Regla de alcance",
                "keywords" => ["alcance", "ios", "android", "app movil", "pwa"],
                "content" => "No se desarrollan apps moviles nativas por ahora. Si piden Android o iOS nativo, explicar limite con respeto y proponer alternativa web/PWA por fases segun objetivo."
            ],
            [
                "id" => "cap-stack-policy",
                "title" => "Politica de stack",
                "keywords" => ["stack", "tecnologia", "framework", "arquitectura"],
                "content" => "Se recomienda stack estable para reducir riesgo y salir rapido. Si el cliente pide stack especifico, se evalua con trade-offs tecnicos y comerciales."
            ],
            [
                "id" => "cap-mobile-limit",
                "title" => "Limite movil",
                "keywords" => ["movil", "nativa", "ios", "android"],
                "content" => "No ofrecer desarrollo nativo iOS/Android en esta etapa. Mantener conversacion abierta y reconducir a solucion web viable."
            ],
            [
                "id" => "qualification-questions",
                "title" => "Datos minimos para personalizar",
                "keywords" => ["lead", "datos", "brief", "personalizar"],
                "content" => "Para respuesta personalizada y seguimiento pedir: nombre, email valido, objetivo principal y plazo estimado."
            ],
            [
                "id" => "pricing-answer-rule",
                "title" => "Regla de precios",
                "keywords" => ["precio", "presupuesto", "coste", "inversion"],
                "content" => "No dar precios cerrados en chat. Explicar que el detalle real de inversion se define al validar alcance y se envia por canal de contacto."
            ],
            [
                "id" => "cta-contact",
                "title" => "Contacto y seguimiento",
                "keywords" => ["contacto", "whatsapp", "email", "seguimiento"],
                "content" => "Canales: usar los datos de contacto configurados para el sitio. El seguimiento para propuesta personalizada se hace en menos de 24h habiles."
            ]
        ]
    ];
}

function agent_v2_sanitize_list($value, int $maxItems = 12, int $maxLength = 120): array
{
    if (!is_array($value)) {
        return [];
    }

    $items = [];

    foreach ($value as $item) {
        $text = api_trim_text((string) $item, $maxLength);

        if ($text === "") {
            continue;
        }

        $items[] = $text;

        if (count($items) >= $maxItems) {
            break;
        }
    }

    return array_values(array_unique($items));
}

function agent_v2_knowledge_path(array $config): string
{
    $relativePath = trim((string) ($config["CHAT_KNOWLEDGE_PATH"] ?? "./knowledge/motor.json"));

    if ($relativePath === "") {
        $relativePath = "./knowledge/motor.json";
    }

    $resolvedPath = api_resolve_storage_path($relativePath);

    if (is_dir($resolvedPath)) {
        return $resolvedPath . DIRECTORY_SEPARATOR . "motor.json";
    }

    if (preg_match("/\.json$/i", $resolvedPath) === 1) {
        return $resolvedPath;
    }

    return $resolvedPath . DIRECTORY_SEPARATOR . "motor.json";
}

function agent_v2_normalize_motor_blocks(array $blocks): array
{
    $normalized = [];

    foreach ($blocks as $index => $block) {
        if (!is_array($block)) {
            continue;
        }

        $content = api_trim_text((string) ($block["content"] ?? ""), 900);

        if ($content === "") {
            continue;
        }

        $idRaw = api_trim_text((string) ($block["id"] ?? ("motor-block-" . $index)), 80);
        $id = preg_replace("/[^a-z0-9._-]+/i", "-", strtolower($idRaw));
        $id = trim((string) $id, "-");

        if ($id === "") {
            $id = "motor-block-" . $index;
        }

        $normalized[] = [
            "id" => $id,
            "title" => api_trim_text((string) ($block["title"] ?? "Bloque de contexto"), 120),
            "content" => $content,
            "keywords" => agent_v2_sanitize_list($block["keywords"] ?? [], 18, 60)
        ];

        if (count($normalized) >= 48) {
            break;
        }
    }

    if (!empty($normalized)) {
        return $normalized;
    }

    $defaults = agent_v2_default_motor_guide();
    return $defaults["blocks"];
}

function agent_v2_normalize_motor_policy(array $policy): array
{
    $defaults = agent_v2_default_motor_guide()["policy"];

    $merged = array_replace_recursive($defaults, $policy);

    return [
        "assistantName" => api_trim_text((string) ($merged["assistantName"] ?? $defaults["assistantName"]), 80),
        "ownerName" => api_trim_text((string) ($merged["ownerName"] ?? $defaults["ownerName"]), 80),
        "toneStyle" => api_trim_text((string) ($merged["toneStyle"] ?? $defaults["toneStyle"]), 220),
        "toneCloseness" => api_trim_text((string) ($merged["toneCloseness"] ?? $defaults["toneCloseness"]), 140),
        "maxAdvanceQuestions" => max(1, min(2, (int) ($merged["maxAdvanceQuestions"] ?? $defaults["maxAdvanceQuestions"]))),
        "noFixedPrices" => (bool) ($merged["noFixedPrices"] ?? $defaults["noFixedPrices"]),
        "noGuaranteedResults" => (bool) ($merged["noGuaranteedResults"] ?? $defaults["noGuaranteedResults"]),
        "transparentWhenUnknown" => (bool) ($merged["transparentWhenUnknown"] ?? $defaults["transparentWhenUnknown"]),
        "outOfScopeReframe" => api_trim_text((string) ($merged["outOfScopeReframe"] ?? $defaults["outOfScopeReframe"]), 260),
        "allowedServices" => agent_v2_sanitize_list($merged["allowedServices"] ?? [], 18, 80),
        "outOfScopeServices" => agent_v2_sanitize_list($merged["outOfScopeServices"] ?? [], 18, 80),
        "minimumLeadFields" => agent_v2_sanitize_list($merged["minimumLeadFields"] ?? [], 8, 40),
        "fallbackReply" => api_trim_text((string) ($merged["fallbackReply"] ?? $defaults["fallbackReply"]), 280)
    ];
}

function agent_v2_load_motor_guide(array $config): array
{
    static $cache = [];
    $filePath = agent_v2_knowledge_path($config);

    if (isset($cache[$filePath]) && is_array($cache[$filePath])) {
        return $cache[$filePath];
    }

    $defaults = agent_v2_default_motor_guide();
    $raw = @file_get_contents($filePath);

    if (!is_string($raw) || $raw === "") {
        $cache[$filePath] = $defaults;
        return $defaults;
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        $cache[$filePath] = $defaults;
        return $defaults;
    }

    $guide = array_replace_recursive($defaults, $decoded);
    $guide["document"] = api_trim_text((string) ($guide["document"] ?? $defaults["document"]), 120);
    $guide["priority"] = api_trim_text((string) ($guide["priority"] ?? $defaults["priority"]), 20);
    $guide["version"] = max(1, (int) ($guide["version"] ?? $defaults["version"]));
    $guide["policy"] = agent_v2_normalize_motor_policy(is_array($guide["policy"] ?? null) ? $guide["policy"] : []);
    $guide["blocks"] = agent_v2_normalize_motor_blocks(is_array($guide["blocks"] ?? null) ? $guide["blocks"] : []);

    if ($guide["document"] === "") {
        $guide["document"] = $defaults["document"];
    }

    if ($guide["priority"] === "") {
        $guide["priority"] = $defaults["priority"];
    }

    $cache[$filePath] = $guide;
    return $guide;
}

function agent_v2_motor_policy(array $config): array
{
    $guide = agent_v2_load_motor_guide($config);
    return is_array($guide["policy"] ?? null) ? $guide["policy"] : agent_v2_default_motor_guide()["policy"];
}

function agent_v2_load_knowledge_catalog(array $config): array
{
    $guide = agent_v2_load_motor_guide($config);

    return [[
        "document" => (string) ($guide["document"] ?? "assistant-motor"),
        "priority" => (string) ($guide["priority"] ?? "high"),
        "blocks" => is_array($guide["blocks"] ?? null) ? $guide["blocks"] : []
    ]];
}

function agent_v2_flatten_knowledge_blocks(array $documents): array
{
    $blocks = [];

    foreach ($documents as $document) {
        if (!is_array($document)) {
            continue;
        }

        $documentName = (string) ($document["document"] ?? "document");
        $documentPriority = (string) ($document["priority"] ?? "medium");
        $sourceBlocks = is_array($document["blocks"] ?? null) ? $document["blocks"] : [];

        foreach ($sourceBlocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $content = trim((string) ($block["content"] ?? ""));

            if ($content === "") {
                continue;
            }

            $blocks[] = [
                "id" => (string) ($block["id"] ?? ($documentName . "-" . count($blocks))),
                "title" => trim((string) ($block["title"] ?? "Bloque de contexto")),
                "content" => $content,
                "keywords" => is_array($block["keywords"] ?? null) ? $block["keywords"] : [],
                "document" => $documentName,
                "priority" => $documentPriority
            ];
        }
    }

    return $blocks;
}
