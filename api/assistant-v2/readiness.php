<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "memory-store.php";

function agent_v2_readiness_threshold(array $config): int
{
    $raw = (int) ($config["CHAT_V2_READINESS_THRESHOLD"] ?? 70);
    return max(45, min(95, $raw));
}

function agent_v2_readiness_score(array $lead): int
{
    $weights = [
        "projectType" => 24,
        "problem" => 24,
        "objective" => 22,
        "timeline" => 18,
        "scopeNotes" => 4,
        "budgetHint" => 4,
        "urgency" => 4
    ];

    $score = 0;

    foreach ($weights as $field => $weight) {
        if (trim((string) ($lead[$field] ?? "")) !== "") {
            $score += $weight;
        }
    }

    return max(0, min(100, $score));
}

function agent_v2_is_price_intent(string $message): bool
{
    $text = strtolower($message);

    foreach (["precio", "presupuesto", "coste", "cuanto", "tarifa", "inversion"] as $needle) {
        if (strpos($text, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function agent_v2_normalize_intent_text(string $message): string
{
    $value = trim($message);

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

function agent_v2_is_confirmation_intent(string $message): bool
{
    $text = agent_v2_normalize_intent_text($message);

    if ($text === "") {
        return false;
    }

    $hardNegatives = [
        '/\b(no|nunca|jamas)\s+(enviar|envies|mandar|mandes|pasar|pases|tramitar|tramites)\b/',
        '/\b(no|todavia|aun|primero|despues|luego|espera)\b/'
    ];

    foreach ($hardNegatives as $pattern) {
        if (preg_match($pattern, $text) === 1) {
            return false;
        }
    }

    $shortApprovals = ["si", "dale", "ok", "okay", "adelante", "procede", "hazlo"];

    if (in_array($text, $shortApprovals, true)) {
        return true;
    }

    $hasApproval = preg_match('/\b(si|dale|ok|okay|adelante|procede|confirmo|autorizo|listo|perfecto|hazlo)\b/', $text) === 1;
    $hasSendVerb = preg_match('/\b(enviar|envia|enviala|mandar|manda|pasar|pasala|tramitar|tramita)\b/', $text) === 1;
    $mentionsQuote = preg_match('/\b(cotizacion|presupuesto|propuesta|solicitud)\b/', $text) === 1;

    if ($hasSendVerb && ($hasApproval || $mentionsQuote)) {
        return true;
    }

    if ($mentionsQuote && preg_match('/\b(confirmo|autorizo|procede|adelante|si)\b/', $text) === 1) {
        return true;
    }

    return false;
}

function agent_v2_brief_is_complete(array $lead): bool
{
    return empty(agent_v2_missing_brief_fields($lead));
}

function agent_v2_brief_has_minimum_for_handoff(array $lead): bool
{
    $projectType = trim((string) ($lead["projectType"] ?? ""));
    $objective = trim((string) ($lead["objective"] ?? ""));
    $problem = trim((string) ($lead["problem"] ?? ""));
    $timeline = trim((string) ($lead["timeline"] ?? ""));
    $scopeNotes = trim((string) ($lead["scopeNotes"] ?? ""));

    if ($projectType === "" || $objective === "") {
        return false;
    }

    return $problem !== "" || $timeline !== "" || $scopeNotes !== "";
}

function agent_v2_scope_is_supported(array $lead): bool
{
    $projectType = trim((string) ($lead["projectType"] ?? ""));
    return $projectType !== "mobile-app-request";
}

function agent_v2_needs_scope_reframe(array $lead): bool
{
    return !agent_v2_scope_is_supported($lead);
}

function agent_v2_can_send_quote(array $lead, int $score, array $config): bool
{
    return $score >= agent_v2_readiness_threshold($config)
        && agent_v2_brief_has_minimum_for_handoff($lead)
        && agent_v2_scope_is_supported($lead)
        && agent_v2_contact_is_valid($lead);
}

function agent_v2_lead_stage(array $memory, int $score, bool $canSendQuote, array $config): string
{
    if ((bool) ($memory["conversation"]["quoteSent"] ?? false)) {
        return "sent";
    }

    if ($canSendQuote) {
        return "ready_to_send";
    }

    if ($score >= agent_v2_readiness_threshold($config)) {
        return "ready_to_close";
    }

    return "collecting";
}
