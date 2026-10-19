<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "memory-store.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "extractor.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "readiness.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "close-manager.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "retrieval.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "llm-client.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "schema-validator.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "policy-guard.php";

function agent_v2_field_labels(array $fields): array
{
    $map = [
        "projectType" => "tipo de proyecto",
        "problem" => "problema actual",
        "objective" => "objetivo principal",
        "timeline" => "plazo estimado",
        "contact.name" => "nombre",
        "contact.email" => "correo"
    ];

    $labels = [];

    foreach ($fields as $field) {
        if (isset($map[$field])) {
            $labels[] = $map[$field];
        }
    }

    return $labels;
}

function agent_v2_human_missing_text(array $fields): string
{
    $labels = agent_v2_field_labels($fields);

    if (empty($labels)) {
        return "";
    }

    if (count($labels) === 1) {
        return $labels[0];
    }

    if (count($labels) === 2) {
        return $labels[0] . " y " . $labels[1];
    }

    return implode(", ", array_slice($labels, 0, -1)) . " y " . $labels[count($labels) - 1];
}

function agent_v2_normalize_compare_text(string $text): string
{
    $value = trim($text);

    if ($value === "") {
        return "";
    }

    if (function_exists("mb_strtolower")) {
        $value = mb_strtolower($value, "UTF-8");
    } else {
        $value = strtolower($value);
    }

    if (function_exists("iconv")) {
        $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $value);

        if (is_string($ascii) && $ascii !== "") {
            $value = $ascii;
        }
    }

    $value = preg_replace("/[^a-z0-9\s]/", " ", $value);
    $value = preg_replace("/\s+/", " ", (string) $value);
    return trim((string) $value);
}

function agent_v2_detect_conversation_flags(string $message, array $lead): array
{
    $text = agent_v2_normalize_compare_text($message);
    $projectType = trim((string) ($lead["projectType"] ?? ""));
    $stackPreference = trim((string) ($lead["stackPreference"] ?? ""));
    $collaborationMode = trim((string) ($lead["collaborationMode"] ?? ""));

    $mobileAppRequest = $projectType === "mobile-app-request"
        || preg_match('/\b(app movil|aplicacion movil|app nativa|android|ios|react native|flutter|play store|app store)\b/i', $text) === 1;

    $stackQuestion = $stackPreference !== ""
        || preg_match('/\b(stack|tecnologia|framework|arquitectura tecnica|next|react|vue|node|laravel|python|django|php|java|go|mysql|postgres|shopify|aws|azure|gcp|docker)\b/i', $text) === 1;

    $teamQuestion = $collaborationMode !== ""
        || preg_match('/\b(equipo|liderar|tech lead|cto|coordinar|gestionar equipo|staff|squad|arquitectura)\b/i', $text) === 1;

    return [
        "mobileAppRequest" => $mobileAppRequest,
        "stackQuestion" => $stackQuestion,
        "teamQuestion" => $teamQuestion,
        "needsScopeReframe" => $mobileAppRequest
    ];
}

function agent_v2_capability_policy_text(array $policy, array $flags): string
{
    $allowed = is_array($policy["allowedServices"] ?? null) ? $policy["allowedServices"] : [];
    $outOfScope = is_array($policy["outOfScopeServices"] ?? null) ? $policy["outOfScopeServices"] : [];

    $lines = [
        "Capacidades base: " . (!empty($allowed) ? implode(", ", $allowed) : "landing pages, webs corporativas, sistemas internos, APIs e integraciones, Shopify y bases de datos"),
        "Fuera de alcance actual: " . (!empty($outOfScope) ? implode(", ", $outOfScope) : "apps moviles nativas Android/iOS"),
        "Flexibilidad tecnica: se puede evaluar stack solicitado por cliente cuando el caso lo justifica.",
        "Colaboracion: en proyectos complejos puede evaluarse coordinacion tecnica con equipo externo como opcion excepcional."
    ];

    if (!empty($flags["mobileAppRequest"])) {
        $lines[] = "Caso actual detectado: solicitud de app movil. Debes aclarar limite y reconducir a alternativa web/PWA por fases, preguntando objetivo de negocio.";
    }

    if (!empty($flags["stackQuestion"])) {
        $lines[] = "Caso actual detectado: consulta de stack. Responder con transparencia: stack recomendado, alternativas viables y trade-offs, sin inventar certezas.";
    }

    if (!empty($flags["teamQuestion"])) {
        $lines[] = "Caso actual detectado: colaboracion con equipo. Explicar alcance real (colaboracion/coordinacion tecnica) y condiciones de complejidad/presupuesto.";
    }

    return implode("\n", $lines);
}

function agent_v2_action_guidance(string $nextAction, string $closeAttempt, array $missingBrief, array $missingContact, array $flags = []): string
{
    $briefLabel = agent_v2_field_labels($missingBrief);
    $contactLabel = agent_v2_field_labels($missingContact);
    $firstBrief = !empty($briefLabel) ? $briefLabel[0] : "contexto del proyecto";

    if ($nextAction === "close_soft") {
        if ($closeAttempt === "soft1") {
            return "Responder primero la duda del usuario y luego pedir de forma natural nombre y correo para preparar propuesta real.";
        }

        return "Resolver la duda y hacer un segundo intento suave de contacto. Explicar valor concreto de compartir nombre y correo.";
    }

    if ($nextAction === "close_final") {
        if (empty($contactLabel)) {
            return "Pedir confirmacion breve para enviar cotizacion real. Si confirma, indicar que se enviara a Andres.";
        }

        return "Entregar mini resumen y dejar CTA comercial abierta sin presionar. Invitar a compartir nombre y correo cuando quiera avanzar.";
    }

    if ($nextAction === "clarify") {
        return "Responder con naturalidad y hacer solo una pregunta corta para completar " . $firstBrief . ".";
    }

    if ($nextAction === "reframe") {
        return "Explicar limite real sin cortar conversacion. Si piden app movil, aclarar que no se desarrolla nativa por ahora, preguntar objetivo y proponer alternativa web/PWA por fases.";
    }

    if ($nextAction === "handoff") {
        return "Confirmar estado de envio de forma clara y cercana. Si falta algo, pedirlo sin tono robotico.";
    }

    if (!empty($flags["stackQuestion"])) {
        return "Responder la duda de stack con precision: que stack actual encaja, que opciones flexibles existen y que trade-offs hay, sin prometer de mas.";
    }

    return "Conversar de forma natural, resolver dudas y avanzar con tacto hacia una propuesta comercial.";
}

function agent_v2_default_suggestions(string $nextAction, array $flags, array $missingBrief, array $missingContact): array
{
    if (!empty($flags["mobileAppRequest"])) {
        return [
            "Te cuento el objetivo que buscaba con la app",
            "Vemos alternativa web/PWA por fases",
            "Te comparto plazo y prioridad"
        ];
    }

    if ($nextAction === "close_soft" || $nextAction === "close_final") {
        return [
            "Comparto mi nombre y correo",
            "Confirmo enviar cotizacion",
            "Antes quiero resolver una duda"
        ];
    }

    if ($nextAction === "clarify" && !empty($missingBrief)) {
        return [
            "Te cuento el problema principal",
            "Te digo el objetivo y plazo",
            "Prefiero hacerlo por fases"
        ];
    }

    if (!empty($flags["stackQuestion"])) {
        return [
            "Tengo stack preferido",
            "Evaluamos trade-offs tecnicos",
            "Priorizamos salida rapida"
        ];
    }

    return [
        "Te explico mi caso en 2 lineas",
        "Tengo duda de alcance y stack",
        "Quiero avanzar a cotizacion"
    ];
}

function agent_v2_build_system_prompt(array $params): string
{
    $contextText = trim((string) ($params["contextText"] ?? ""));
    $memorySummary = trim((string) ($params["memorySummary"] ?? ""));
    $clientContext = trim((string) ($params["clientContext"] ?? ""));
    $missingBrief = is_array($params["missingBrief"] ?? null) ? $params["missingBrief"] : [];
    $missingContact = is_array($params["missingContact"] ?? null) ? $params["missingContact"] : [];
    $nextAction = trim((string) ($params["nextAction"] ?? "explore"));
    $closeAttempt = trim((string) ($params["closeAttempt"] ?? "none"));
    $readinessScore = (int) ($params["readinessScore"] ?? 0);
    $threshold = (int) ($params["threshold"] ?? 70);
    $flags = is_array($params["flags"] ?? null) ? $params["flags"] : [];
    $capabilityPolicy = trim((string) ($params["capabilityPolicy"] ?? ""));
    $motorPolicy = is_array($params["motorPolicy"] ?? null) ? $params["motorPolicy"] : [];
    $assistantName = api_trim_text((string) ($motorPolicy["assistantName"] ?? "Andro"), 60);
    $ownerName = api_trim_text((string) ($motorPolicy["ownerName"] ?? "Andres Robles"), 60);
    $toneStyle = api_trim_text((string) ($motorPolicy["toneStyle"] ?? "Consultivo directo y claro."), 220);
    $toneCloseness = api_trim_text((string) ($motorPolicy["toneCloseness"] ?? "Cercano sin informalidad."), 160);
    $maxAdvanceQuestions = max(1, min(2, (int) ($motorPolicy["maxAdvanceQuestions"] ?? 1)));
    $minimumLeadFields = is_array($motorPolicy["minimumLeadFields"] ?? null) ? $motorPolicy["minimumLeadFields"] : [];
    $briefLabels = agent_v2_field_labels($missingBrief);
    $contactLabels = agent_v2_field_labels($missingContact);

    $schema = [
        "reply" => "texto breve y natural en espanol",
        "lead_update" => [
            "projectType" => "",
            "requestedPlatform" => "",
            "stackPreference" => "",
            "collaborationMode" => "",
            "problem" => "",
            "objective" => "",
            "timeline" => "",
            "scopeNotes" => "",
            "budgetHint" => "",
            "urgency" => "",
            "contact" => [
                "name" => "",
                "email" => "",
                "phone" => ""
            ]
        ],
        "suggestions" => [
            "opcion 1",
            "opcion 2",
            "opcion 3"
        ]
    ];

    $rules = [
        "Eres " . ($assistantName !== "" ? $assistantName : "Andro") . ", asistente comercial de " . ($ownerName !== "" ? $ownerName : "Andres Robles") . ".",
        "Objetivo: resolver dudas y guiar de forma natural hacia una cotizacion real.",
        "No des precios cerrados ni promesas garantizadas.",
        "No fuerces un guion ni interrogatorio. Haz maximo " . $maxAdvanceQuestions . " pregunta de avance por turno.",
        "No uses adulaciones ni halagos innecesarios.",
        "Si un dato no esta en contexto o memoria, dilo con transparencia y evita inventar.",
        "Si falta contacto y toca cierre comercial, pedir nombre y correo con tacto.",
        "Si el usuario no comparte contacto, mantener la conversacion abierta con CTA amable.",
        "Estilo de comunicacion: " . ($toneStyle !== "" ? $toneStyle : "Consultivo directo y claro."),
        "Cercania objetivo: " . ($toneCloseness !== "" ? $toneCloseness : "Cercano sin informalidad."),
        "Responde en espanol, 1-4 frases, sin jerga innecesaria.",
        "Entrega SIEMPRE JSON valido, sin markdown, sin texto extra fuera del JSON."
    ];

    $sections = [
        implode("\n", $rules),
        "Contexto de negocio:\n" . ($contextText !== "" ? $contextText : "Sin contexto adicional."),
        "Politica de capacidades:\n" . ($capabilityPolicy !== "" ? $capabilityPolicy : "Sin politica adicional."),
        "Contexto tecnico de la vista:\n" . ($clientContext !== "" ? $clientContext : "Sin contexto tecnico adicional."),
        "Memoria comercial:\n" . ($memorySummary !== "" ? $memorySummary : "Sin memoria comercial."),
        "Readiness actual: " . $readinessScore . "/100 (umbral de cierre: " . $threshold . ")",
        "Datos minimos para personalizacion y seguimiento: " . (!empty($minimumLeadFields) ? implode(", ", $minimumLeadFields) : "contact.name, contact.email, objective, timeline"),
        "Faltan datos de briefing: " . (!empty($briefLabels) ? implode(", ", $briefLabels) : "ninguno"),
        "Faltan datos de contacto: " . (!empty($contactLabels) ? implode(", ", $contactLabels) : "ninguno"),
        "Siguiente accion recomendada: " . $nextAction . " (intento de cierre: " . $closeAttempt . ")",
        "Flags detectadas: " . json_encode($flags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        "Guia de respuesta para este turno: " . agent_v2_action_guidance($nextAction, $closeAttempt, $missingBrief, $missingContact, $flags),
        "Esquema JSON de salida:\n" . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ];

    return implode("\n\n", $sections);
}

function agent_v2_quote_summary_text(array $memory): string
{
    $lead = is_array($memory["lead"] ?? null) ? $memory["lead"] : [];
    $contact = is_array($lead["contact"] ?? null) ? $lead["contact"] : [];

    $lines = [
        "Nueva solicitud de cotizacion desde asistente web v2.",
        "",
        "Nombre: " . (string) ($contact["name"] ?? ""),
        "Email: " . (string) ($contact["email"] ?? ""),
        "Telefono: " . ((string) ($contact["phone"] ?? "") !== "" ? (string) $contact["phone"] : "(no informado)"),
        "Tipo de proyecto: " . (string) ($lead["projectType"] ?? ""),
        "Problema actual: " . (string) ($lead["problem"] ?? ""),
        "Objetivo principal: " . (string) ($lead["objective"] ?? ""),
        "Plazo estimado: " . (string) ($lead["timeline"] ?? ""),
        "Notas de alcance: " . (string) ($lead["scopeNotes"] ?? ""),
        "Budget orientativo: " . (string) ($lead["budgetHint"] ?? ""),
        "Urgencia: " . (string) ($lead["urgency"] ?? ""),
        "",
        "Sesion: " . (string) ($memory["sessionId"] ?? "")
    ];

    return implode("\n", $lines);
}

function agent_v2_public_contact_hint(array $config): string
{
    $email = trim((string) ($config["PUBLIC_CONTACT_EMAIL"] ?? ""));
    $whatsApp = trim((string) ($config["PUBLIC_CONTACT_WHATSAPP"] ?? ""));
    $channels = [];

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $channels[] = "email " . $email;
    }

    if ($whatsApp !== "") {
        $channels[] = "WhatsApp " . $whatsApp;
    }

    if (empty($channels)) {
        return "";
    }

    if (count($channels) === 1) {
        return "Tambien puedes contactar por " . $channels[0] . ".";
    }

    return "Tambien puedes contactar por " . implode(" o ", $channels) . ".";
}

function agent_v2_history_messages(array $history, array $memory, int $historyLimit): array
{
    $messages = [];

    if (!empty($history)) {
        foreach ($history as $entry) {
            $messages[] = [
                "role" => $entry["role"],
                "content" => $entry["content"]
            ];
        }

        return $messages;
    }

    $memoryTurns = is_array($memory["turns"] ?? null) ? $memory["turns"] : [];
    $memoryTurns = array_slice($memoryTurns, -$historyLimit);

    foreach ($memoryTurns as $turn) {
        $role = ($turn["role"] ?? "") === "assistant" ? "assistant" : "user";
        $content = api_trim_text((string) ($turn["content"] ?? ""), 1000);

        if ($content === "") {
            continue;
        }

        $messages[] = [
            "role" => $role,
            "content" => $content
        ];
    }

    return $messages;
}

function agent_v2_lead_payload(array $lead): array
{
    $contact = is_array($lead["contact"] ?? null) ? $lead["contact"] : [];

    return [
        "projectType" => (string) ($lead["projectType"] ?? ""),
        "requestedPlatform" => (string) ($lead["requestedPlatform"] ?? ""),
        "stackPreference" => (string) ($lead["stackPreference"] ?? ""),
        "collaborationMode" => (string) ($lead["collaborationMode"] ?? ""),
        "problem" => (string) ($lead["problem"] ?? ""),
        "objective" => (string) ($lead["objective"] ?? ""),
        "timeline" => (string) ($lead["timeline"] ?? ""),
        "scopeNotes" => (string) ($lead["scopeNotes"] ?? ""),
        "budgetHint" => (string) ($lead["budgetHint"] ?? ""),
        "urgency" => (string) ($lead["urgency"] ?? ""),
        "contact" => [
            "name" => (string) ($contact["name"] ?? ""),
            "email" => (string) ($contact["email"] ?? ""),
            "phone" => (string) ($contact["phone"] ?? ""),
            "status" => (string) ($contact["status"] ?? "not_requested")
        ]
    ];
}

function agent_v2_handle_chat(array $config): never
{
    api_require_post();

    if (!api_rate_limit_allow(
        "chat:" . api_client_ip(),
        (int) ($config["CHAT_RATE_LIMIT_MAX"] ?? 12),
        (int) ($config["CHAT_RATE_LIMIT_WINDOW_SECONDS"] ?? 60)
    )) {
        api_error_response(429, "RATE_LIMIT", "Has alcanzado el limite de solicitudes. Intenta de nuevo en unos segundos.");
    }

    $body = api_get_json_body();
    api_validate_text_fields($body, ['message' => 3600, 'sessionId' => 90]);
    if ((isset($body['history']) && (!is_array($body['history']) || !array_is_list($body['history']) || count($body['history']) > 24))
        || (isset($body['context']) && (!is_array($body['context']) || count($body['context']) > 10))) {
        api_error_response(422, 'VALIDATION_ERROR', 'Contexto o historial invalido.');
    }
    if (!api_rate_limit_allow('chat-global-daily', (int) ($config['CHAT_GLOBAL_DAILY_MAX'] ?? 200), 86400)) {
        api_error_response(429, 'RATE_LIMIT_GLOBAL', 'Se alcanzo el limite global diario del asistente.');
    }
    $message = api_trim_text((string) ($body["message"] ?? ""), 900);
    $sessionId = api_chat_session_id();
    $historyLimit = max(4, min(12, (int) ($config["CHAT_HISTORY_MAX_ITEMS"] ?? 10)));
    $history = api_normalize_history($body["history"] ?? [], $historyLimit);
    $contextPayload = is_array($body["context"] ?? null) ? $body["context"] : [];
    $memoryMaxTurns = max(6, min(24, (int) ($config["CHAT_MEMORY_MAX_TURNS"] ?? 16)));
    if (($config['APP_MODE'] ?? 'live') === 'demo') {
        $memory = agent_v2_default_memory($sessionId);
        api_json_response(200, [
            'reply' => 'Modo demostracion: no se ha consultado una IA ni enviado una solicitud. Puedes explorar la interfaz con un caso ficticio.',
            'sessionId' => $sessionId, 'source' => 'demo', 'suggestions' => [],
            'lead' => agent_v2_lead_payload($memory['lead']), 'missingFields' => [],
            'readinessScore' => 0, 'nextBestAction' => 'explore', 'closeAttempt' => 'none',
            'canSendQuote' => false, 'leadStage' => 'collecting', 'leadStatus' => 'demo', 'scopeStatus' => 'supported',
        ]);
    }
    $sessionLock = agent_v2_acquire_memory_lock($sessionId, $config);
    $memory = agent_v2_load_memory($sessionId, $config);
    $lead = is_array($memory["lead"] ?? null) ? $memory["lead"] : [];

    if ($message !== "") {
        $lead = agent_v2_merge_lead($lead, agent_v2_extract_updates_from_message($message));
    }

    $memory["lead"] = $lead;
    $threshold = agent_v2_readiness_threshold($config);
    $readinessScore = agent_v2_readiness_score($lead);
    $missingBrief = agent_v2_missing_brief_fields($lead);
    $missingContact = agent_v2_missing_contact_fields($lead);
    $contactComplete = empty($missingContact);
    $quoteSent = (bool) ($memory["conversation"]["quoteSent"] ?? false);
    $lastAction = (string) ($memory["conversation"]["lastAction"] ?? "explore");
    $motorPolicy = agent_v2_motor_policy($config);
    $canSendQuote = agent_v2_can_send_quote($lead, $readinessScore, $config);
    $priceIntent = agent_v2_is_price_intent($message);
    $confirmIntent = agent_v2_is_confirmation_intent($message);
    $flags = agent_v2_detect_conversation_flags($message, $lead);
    $needsScopeReframe = (bool) ($flags["needsScopeReframe"] ?? false) || agent_v2_needs_scope_reframe($lead);
    $capabilityPolicy = agent_v2_capability_policy_text($motorPolicy, $flags);

    $previewAction = agent_v2_next_action([
        "quoteSent" => $quoteSent,
        "readinessScore" => $readinessScore,
        "threshold" => $threshold,
        "contactComplete" => $contactComplete,
        "canSendQuote" => $canSendQuote,
        "priceIntent" => $priceIntent,
        "confirmIntent" => $confirmIntent,
        "needsReframe" => $needsScopeReframe,
        "lastAction" => $lastAction,
        "closeAttempts" => (int) ($memory["conversation"]["closeAttempts"] ?? 0),
        "maxSoftAttempts" => (int) ($config["CHAT_V2_CLOSE_SOFT_ATTEMPTS"] ?? 2),
        "missingBriefFields" => $missingBrief
    ]);

    $contextText = agent_v2_retrieve_context_text($config, $message);
    $memorySummary = agent_v2_memory_summary($memory);
    $contextParts = [];

    foreach ($contextPayload as $key => $value) {
        if (!is_scalar($value)) {
            continue;
        }

        $safeKey = api_trim_text((string) $key, 30);
        $safeValue = api_trim_text((string) $value, 80);

        if ($safeKey !== "" && $safeValue !== "") {
            $contextParts[] = $safeKey . "=" . $safeValue;
        }
    }

    $clientContextText = !empty($contextParts) ? implode(" | ", $contextParts) : "";

    if ($message === "") {
        $reply = "Cuentame que quieres construir o mejorar y te ayudo a aterrizar una propuesta por fases.";

        if (trim((string) ($motorPolicy["fallbackReply"] ?? "")) !== "") {
            $reply = "Cuentame que quieres construir o mejorar. " . trim((string) ($motorPolicy["fallbackReply"] ?? ""));
        }

        agent_v2_append_turn($memory, "assistant", $reply, $memoryMaxTurns);
        $memoryError = null;
        if (!agent_v2_save_memory($memory, $config, $memoryError)) api_error_response(503, 'MEMORY_UNAVAILABLE', 'No se pudo conservar la conversacion.');

        api_json_response(200, [
            "reply" => $reply,
            "sessionId" => $sessionId,
            "source" => "api-v2",
            "suggestions" => agent_v2_default_suggestions((string) ($previewAction["nextBestAction"] ?? "explore"), $flags, $missingBrief, $missingContact),
            "lead" => agent_v2_lead_payload($memory["lead"]),
            "missingFields" => array_values(array_merge($missingBrief, $missingContact)),
            "readinessScore" => $readinessScore,
            "nextBestAction" => $previewAction["nextBestAction"],
            "closeAttempt" => $previewAction["closeAttempt"],
            "canSendQuote" => $canSendQuote,
            "leadStage" => agent_v2_lead_stage($memory, $readinessScore, $canSendQuote, $config),
            "leadStatus" => $quoteSent ? "sent-to-andres" : ($canSendQuote ? "ready-to-confirm" : "collecting-data"),
            "scopeStatus" => $needsScopeReframe ? "needs-reframe" : "supported"
        ]);
    }

    $systemPrompt = agent_v2_build_system_prompt([
        "contextText" => $contextText,
        "capabilityPolicy" => $capabilityPolicy,
        "flags" => $flags,
        "clientContext" => $clientContextText,
        "memorySummary" => $memorySummary,
        "missingBrief" => $missingBrief,
        "missingContact" => $missingContact,
        "nextAction" => $previewAction["nextBestAction"],
        "closeAttempt" => $previewAction["closeAttempt"],
        "readinessScore" => $readinessScore,
        "threshold" => $threshold,
        "motorPolicy" => $motorPolicy
    ]);

    $messages = [
        [
            "role" => "system",
            "content" => $systemPrompt
        ]
    ];

    foreach (agent_v2_history_messages($history, $memory, $historyLimit) as $item) {
        $messages[] = $item;
    }

    $messages[] = [
        "role" => "user",
        "content" => $message
    ];

    $temperature = (float) ($config["CHAT_V2_TEMPERATURE"] ?? 0.35);
    $maxTokens = (int) ($config["CHAT_V2_MAX_TOKENS"] ?? 900);
    $rawModelReply = agent_v2_request_model_reply($config, $messages, $temperature, $maxTokens);
    $modelPayload = agent_v2_parse_json_object($rawModelReply);
    $normalized = agent_v2_normalize_model_payload($modelPayload);

    $reply = trim((string) $normalized["reply"]);

    if ($reply === "") {
        $reply = trim((string) ($motorPolicy["fallbackReply"] ?? ""));
    }

    if ($reply === "") {
        $reply = "Entendido. Cuentame un poco mas y te ayudo a concretar la propuesta.";
    }

    $missingBriefBeforeModel = $missingBrief;
    $missingContactBeforeModel = $missingContact;
    $memory["lead"] = agent_v2_merge_lead($memory["lead"], $normalized["leadUpdate"]);
    $lead = $memory["lead"];
    $readinessScore = agent_v2_readiness_score($lead);
    $missingBrief = agent_v2_missing_brief_fields($lead);
    $missingContact = agent_v2_missing_contact_fields($lead);
    $contactComplete = empty($missingContact);
    $canSendQuote = agent_v2_can_send_quote($lead, $readinessScore, $config);
    $flags = agent_v2_detect_conversation_flags($message, $lead);
    $needsScopeReframe = (bool) ($flags["needsScopeReframe"] ?? false) || agent_v2_needs_scope_reframe($lead);

    $madeProgress = count($missingBrief) < count($missingBriefBeforeModel)
        || count($missingContact) < count($missingContactBeforeModel);

    if ($madeProgress) {
        $memory["conversation"]["closeAttempts"] = 0;
    }

    $finalAction = agent_v2_next_action([
        "quoteSent" => (bool) ($memory["conversation"]["quoteSent"] ?? false),
        "readinessScore" => $readinessScore,
        "threshold" => $threshold,
        "contactComplete" => $contactComplete,
        "canSendQuote" => $canSendQuote,
        "priceIntent" => $priceIntent,
        "confirmIntent" => $confirmIntent,
        "needsReframe" => $needsScopeReframe,
        "lastAction" => (string) ($memory["conversation"]["lastAction"] ?? "explore"),
        "closeAttempts" => (int) ($memory["conversation"]["closeAttempts"] ?? 0),
        "maxSoftAttempts" => (int) ($config["CHAT_V2_CLOSE_SOFT_ATTEMPTS"] ?? 2),
        "missingBriefFields" => $missingBrief
    ]);

    if ((bool) ($finalAction["incrementCloseAttempt"] ?? false)) {
        $memory["conversation"]["closeAttempts"] = (int) ($memory["conversation"]["closeAttempts"] ?? 0) + 1;
        $memory["conversation"]["lastCloseAttemptAt"] = gmdate("c");
    }

    $memory["conversation"]["lastAction"] = (string) ($finalAction["nextBestAction"] ?? "explore");

    $quoteSendResult = "none";
    $quoteSendReason = "";

    $readyToHandoff = !(bool) ($memory["conversation"]["quoteSent"] ?? false)
        && $lastAction === 'close_final'
        && $confirmIntent
        && $canSendQuote
        && (string) ($finalAction["nextBestAction"] ?? "") === "handoff"
        && (string) ($finalAction["closeAttempt"] ?? "") === "final";

    if ($readyToHandoff) {
        $notifyEmail = trim((string) ($config["CHAT_QUOTE_NOTIFY_EMAIL"] ?? ""));
        $publicContactHint = agent_v2_public_contact_hint($config);
        $subject = "Nueva solicitud de cotizacion - " . (string) ($lead["contact"]["name"] ?? "Lead web");
        $mailBody = agent_v2_quote_summary_text($memory);
        $quoteStoragePath = api_resolve_storage_path((string) ($config["CHAT_QUOTE_STORAGE_PATH"] ?? "../storage/quotes.ndjson"));
        $quoteStorageError = null;
        $quoteRecord = [
            "id" => api_generate_id("quote"),
            "createdAt" => gmdate("c"),
            "sessionId" => (string) ($memory["sessionId"] ?? $sessionId),
            "source" => "assistant-v2",
            "lead" => agent_v2_lead_payload($lead)
        ];
        $quoteStored = api_append_json_line($quoteStoragePath, $quoteRecord, $quoteStorageError);
        $mailSent = api_try_send_plain_mail($notifyEmail, $subject, $mailBody);

        if ($mailSent) {
            $memory["conversation"]["quoteSent"] = true;
            $memory["conversation"]["quoteSentAt"] = gmdate("c");
            $quoteSendResult = "sent";
            $reply = rtrim($reply) . "\n\nListo. Ya envie tu solicitud a Andres para cotizacion real.";
        } elseif ($quoteStored) {
            $memory["conversation"]["quoteSent"] = true;
            $memory["conversation"]["quoteSentAt"] = gmdate("c");
            $quoteSendResult = "queued";
            $quoteSendReason = "mail_delivery_failed";
            $reply = rtrim($reply) . "\n\nNo pude enviar el correo ahora mismo, pero tu solicitud quedo guardada para seguimiento.";

            if ($publicContactHint !== "") {
                $reply = rtrim($reply) . "\n\n" . $publicContactHint;
            }
        } else {
            $quoteSendResult = "failed";
            $quoteSendReason = "mail_and_storage_failed";
            $reply = rtrim($reply) . "\n\nNo pude enviar ni guardar la solicitud en este momento.";

            if ($publicContactHint !== "") {
                $reply = rtrim($reply) . "\n\n" . $publicContactHint;
            }
        }
    } elseif ($confirmIntent && $canSendQuote && $lastAction !== 'close_final' && !(bool) ($memory['conversation']['quoteSent'] ?? false)) {
        $finalAction['nextBestAction'] = 'close_final';
        $finalAction['closeAttempt'] = 'final';
        $memory['conversation']['lastAction'] = 'close_final';
        $reply = 'Tenemos el briefing y el contacto. Confirmas que deseas enviar la solicitud de cotizacion?';
    } elseif ($confirmIntent && !(bool) ($memory["conversation"]["quoteSent"] ?? false)) {
        if (!agent_v2_contact_is_valid($lead)) {
            $quoteSendResult = "blocked_missing_contact";
            $quoteSendReason = "missing_contact";
            $reply = rtrim($reply) . "\n\nPara enviarla necesito tu nombre y correo de contacto.";
        } elseif (agent_v2_needs_scope_reframe($lead)) {
            $quoteSendResult = "blocked_scope_reframe";
            $quoteSendReason = "unsupported_scope";
            $reply = rtrim($reply) . "\n\nAntes de enviarla tenemos que reconducir el alcance a una solucion web viable para tu objetivo.";
        } elseif (!empty($missingBrief)) {
            $quoteSendResult = "blocked_missing_brief";
            $quoteSendReason = "missing_brief";
            $reply = rtrim($reply) . "\n\nAntes de enviarla necesito concretar " . agent_v2_human_missing_text($missingBrief) . ".";
        } elseif (!$canSendQuote) {
            $quoteSendResult = "blocked_not_ready";
            $quoteSendReason = "not_ready";
        }
    }

    $quoteSent = (bool) ($memory["conversation"]["quoteSent"] ?? false);
    $leadStatus = $quoteSent ? "sent-to-andres" : ($canSendQuote ? "ready-to-confirm" : "collecting-data");
    $baseSuggestions = !empty($normalized["suggestions"])
        ? $normalized["suggestions"]
        : agent_v2_default_suggestions((string) ($finalAction["nextBestAction"] ?? "explore"), $flags, $missingBrief, $missingContact);

    $guard = agent_v2_apply_policy_guard([
        "config" => $config,
        "reply" => $reply,
        "suggestions" => $baseSuggestions,
        "nextAction" => (string) ($finalAction["nextBestAction"] ?? "explore"),
        "missingFields" => array_values(array_merge($missingBrief, $missingContact)),
        "needsScopeReframe" => $needsScopeReframe
    ]);

    $reply = trim((string) ($guard["reply"] ?? $reply));
    $suggestions = is_array($guard["suggestions"] ?? null) && !empty($guard["suggestions"])
        ? $guard["suggestions"]
        : $baseSuggestions;

    agent_v2_append_turn($memory, "user", $message, $memoryMaxTurns);
    agent_v2_append_turn($memory, "assistant", $reply, $memoryMaxTurns);
    $memoryError = null;
    if (!agent_v2_save_memory($memory, $config, $memoryError)) api_error_response(503, 'MEMORY_UNAVAILABLE', 'No se pudo conservar la conversacion.');

    api_json_response(200, [
        "reply" => trim($reply),
        "sessionId" => $sessionId,
        "source" => "api-v2",
        "suggestions" => $suggestions,
        "lead" => agent_v2_lead_payload($memory["lead"]),
        "missingFields" => array_values(array_merge($missingBrief, $missingContact)),
        "readinessScore" => $readinessScore,
        "nextBestAction" => (string) ($finalAction["nextBestAction"] ?? "explore"),
        "closeAttempt" => (string) ($finalAction["closeAttempt"] ?? "none"),
        "canSendQuote" => $canSendQuote,
        "leadStage" => agent_v2_lead_stage($memory, $readinessScore, $canSendQuote, $config),
        "leadStatus" => $leadStatus,
        "scopeStatus" => $needsScopeReframe ? "needs-reframe" : "supported",
        "quoteSendResult" => $quoteSendResult,
        "quoteSendReason" => $quoteSendReason
    ]);
}
