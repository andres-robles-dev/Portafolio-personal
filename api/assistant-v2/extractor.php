<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "memory-store.php";

function agent_v2_extract_email(string $message): string
{
    if (preg_match("/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i", $message, $matches) === 1) {
        return strtolower(trim((string) $matches[0]));
    }

    return "";
}

function agent_v2_extract_phone(string $message): string
{
    if (preg_match("/\+?[0-9][0-9\s().-]{6,24}/", $message, $matches) !== 1) {
        return "";
    }

    $clean = preg_replace("/\s+/", " ", trim((string) $matches[0]));
    return is_string($clean) ? $clean : "";
}

function agent_v2_extract_name(string $message): string
{
    $text = trim($message);

    if ($text === "") {
        return "";
    }

    if (preg_match("/(?:me\s+llamo|soy|mi\s+nombre\s+es)\s+([^,.!?\n]+)/iu", $text, $matches) === 1) {
        $candidate = trim((string) ($matches[1] ?? ""));
    } else {
        $candidate = trim(preg_replace("/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i", " ", $text) ?? "");
    }

    $candidate = preg_replace("/[0-9]/", " ", $candidate);
    $candidate = preg_replace("/[^\p{L}\s'-]/u", " ", (string) $candidate);
    $candidate = preg_replace("/\s+/u", " ", (string) $candidate);
    $candidate = trim((string) $candidate);

    $lower = strtolower($candidate);
    $blocked = [
        "quiero",
        "necesito",
        "presupuesto",
        "cotizacion",
        "web",
        "sistema",
        "api",
        "hola"
    ];

    foreach ($blocked as $item) {
        if ($lower === $item || strpos($lower, $item . " ") === 0) {
            return "";
        }
    }

    if (strlen($candidate) < 2 || strlen($candidate) > 80) {
        return "";
    }

    $words = preg_split("/\s+/u", $candidate, -1, PREG_SPLIT_NO_EMPTY);

    if (!is_array($words) || count($words) > 5) {
        return "";
    }

    return $candidate;
}

function agent_v2_extract_project_type(string $message): string
{
    $text = strtolower($message);

    if (preg_match('/(app movil|aplicacion movil|app nativa|android|ios|react native|flutter|kotlin|swift|play store|app store)/i', $text) === 1) {
        return "mobile-app-request";
    }

    $map = [
        "landing" => "landing-page",
        "shopify" => "ecommerce-shopify",
        "ecommerce" => "ecommerce-shopify",
        "e-commerce" => "ecommerce-shopify",
        "api" => "api-integracion",
        "sistema" => "sistema-interno",
        "erp" => "sistema-interno",
        "saas" => "sistema-interno",
        "web corporativa" => "web-corporativa",
        "web" => "web-corporativa",
        "base de datos" => "bases-de-datos",
        "mysql" => "bases-de-datos",
        "postgres" => "bases-de-datos"
    ];

    foreach ($map as $needle => $value) {
        if (strpos($text, $needle) !== false) {
            return $value;
        }
    }

    return "";
}

function agent_v2_extract_requested_platform(string $message): string
{
    $text = strtolower($message);

    if (preg_match('/(android|play store|kotlin)/i', $text) === 1) {
        return "android";
    }

    if (preg_match('/(ios|iphone|app store|swift)/i', $text) === 1) {
        return "ios";
    }

    if (preg_match('/(app movil|aplicacion movil|react native|flutter)/i', $text) === 1) {
        return "mobile";
    }

    if (preg_match('/(web|pwa|sitio|landing|portal)/i', $text) === 1) {
        return "web";
    }

    return "";
}

function agent_v2_extract_stack_preference(string $message): string
{
    $text = strtolower($message);
    $stacks = [
        "next.js" => ["next", "nextjs"],
        "react" => ["react"],
        "vue" => ["vue"],
        "angular" => ["angular"],
        "node.js" => ["node", "nodejs", "express", "nestjs"],
        "php" => ["php", "laravel", "symfony"],
        "python" => ["python", "django", "fastapi", "flask"],
        "java" => ["java", "spring"],
        "go" => ["golang", "go"],
        "mysql" => ["mysql"],
        "postgresql" => ["postgres", "postgresql"],
        "shopify" => ["shopify"],
        "docker" => ["docker", "contenedor"],
        "aws" => ["aws"],
        "gcp" => ["gcp", "google cloud"],
        "azure" => ["azure"]
    ];

    foreach ($stacks as $name => $needles) {
        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                return $name;
            }
        }
    }

    return "";
}

function agent_v2_extract_collaboration_mode(string $message): string
{
    $text = strtolower($message);

    if (preg_match('/(liderar|tech lead|cto|coordinar equipo|administrar equipo|gestionar equipo|arquitectura)/i', $text) === 1) {
        return "technical-lead";
    }

    if (preg_match('/(equipo|squad|colaborar|staff augmentation|freelance con equipo)/i', $text) === 1) {
        return "team-collaboration";
    }

    return "";
}

function agent_v2_extract_timeline(string $message): string
{
    if (preg_match("/(\d+)\s*(dia|dias|semana|semanas|mes|meses)/i", $message, $matches) === 1) {
        return trim((string) $matches[0]);
    }

    $text = strtolower(trim($message));

    if ($text === "") {
        return "";
    }

    if (preg_match("/(urgente|cuanto antes|lo antes posible|prioridad alta)/i", $text) === 1) {
        return "lo antes posible";
    }

    if (preg_match("/(por definir|sin plazo|flexible)/i", $text) === 1) {
        return "por definir";
    }

    return "";
}

function agent_v2_extract_objective(string $message): string
{
    $text = strtolower($message);

    if (strpos($text, "lead") !== false || strpos($text, "capt") !== false) {
        return "captar leads";
    }

    if (strpos($text, "venta") !== false || strpos($text, "vender") !== false) {
        return "aumentar ventas";
    }

    if (strpos($text, "automat") !== false || strpos($text, "operaci") !== false || strpos($text, "flujo") !== false) {
        return "mejorar operaciones";
    }

    if (preg_match("/(objetivo|quiero|necesito)\b/i", $message) === 1) {
        return api_trim_text(trim($message), 220);
    }

    return "";
}

function agent_v2_extract_problem(string $message): string
{
    $text = trim($message);

    if ($text === "") {
        return "";
    }

    if (preg_match("/(problema|dificultad|bloqueo|no consigo|no tengo|pierdo tiempo|manual|desorden|errores)/i", $text) === 1) {
        return api_trim_text($text, 300);
    }

    return "";
}

function agent_v2_extract_budget_hint(string $message): string
{
    if (preg_match("/(\d+[\d.,]*)\s*(eur|euros|€)/i", $message, $matches) === 1) {
        return trim((string) $matches[0]);
    }

    $text = strtolower($message);

    if (preg_match("/(presupuesto ajustado|presupuesto limitado|sin presupuesto cerrado)/i", $text) === 1) {
        return api_trim_text(trim($message), 120);
    }

    return "";
}

function agent_v2_extract_urgency(string $message): string
{
    $text = strtolower($message);

    if (preg_match("/(urgente|ya|esta semana|esta quincena|cuanto antes)/i", $text) === 1) {
        return "alta";
    }

    if (preg_match("/(sin prisa|cuando se pueda|sin urgencia)/i", $text) === 1) {
        return "baja";
    }

    return "";
}

function agent_v2_extract_scope_notes(string $message): string
{
    $text = trim($message);

    if (strlen($text) < 40) {
        return "";
    }

    return api_trim_text($text, 450);
}

function agent_v2_extract_updates_from_message(string $message): array
{
    $email = agent_v2_extract_email($message);
    $name = agent_v2_extract_name($message);
    $phone = agent_v2_extract_phone($message);

    return [
        "projectType" => agent_v2_extract_project_type($message),
        "requestedPlatform" => agent_v2_extract_requested_platform($message),
        "stackPreference" => agent_v2_extract_stack_preference($message),
        "collaborationMode" => agent_v2_extract_collaboration_mode($message),
        "problem" => agent_v2_extract_problem($message),
        "objective" => agent_v2_extract_objective($message),
        "timeline" => agent_v2_extract_timeline($message),
        "scopeNotes" => agent_v2_extract_scope_notes($message),
        "budgetHint" => agent_v2_extract_budget_hint($message),
        "urgency" => agent_v2_extract_urgency($message),
        "contact" => [
            "name" => $name,
            "email" => $email,
            "phone" => $phone
        ]
    ];
}

function agent_v2_merge_lead(array $lead, array $updates): array
{
    $fields = ["projectType", "requestedPlatform", "stackPreference", "collaborationMode", "problem", "objective", "timeline", "scopeNotes", "budgetHint", "urgency"];

    foreach ($fields as $field) {
        if (!array_key_exists($field, $updates)) {
            continue;
        }

        $value = trim((string) ($updates[$field] ?? ""));

        if ($value !== "") {
            $lead[$field] = api_trim_text($value, 450);
        }
    }

    $contact = is_array($lead["contact"] ?? null) ? $lead["contact"] : [];
    $updatesContact = is_array($updates["contact"] ?? null) ? $updates["contact"] : [];

    foreach (["name", "email", "phone"] as $contactField) {
        $value = trim((string) ($updatesContact[$contactField] ?? ""));

        if ($value === "") {
            continue;
        }

        if ($contactField === "email") {
            $value = strtolower($value);
        }

        $contact[$contactField] = api_trim_text($value, 180);
    }

    $lead["contact"] = $contact;
    agent_v2_touch_contact_status($lead);
    return $lead;
}
