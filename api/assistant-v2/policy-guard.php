<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "knowledge.php";

function agent_v2_policy_field_question(string $field): string
{
    $questions = [
        "projectType" => "Para orientarte bien, que tipo de proyecto quieres lanzar primero?",
        "problem" => "Para afinar la recomendacion, cual es el problema principal que quieres resolver ahora?",
        "objective" => "Cual es el objetivo principal que quieres lograr con este proyecto?",
        "timeline" => "Que plazo estimado tienes para la primera fase?",
        "contact.name" => "Si quieres que te prepare una propuesta personalizada, me compartes tu nombre?",
        "contact.email" => "Me compartes un correo valido para enviarte la propuesta personalizada?"
    ];

    return (string) ($questions[$field] ?? "Cual seria el dato clave que falta para orientarte mejor?");
}

function agent_v2_policy_refine_tone(string $reply): string
{
    $text = trim($reply);

    if ($text === "") {
        return "";
    }

    $replacements = [
        '/\b(excelente|increible|fantastico|genial|brutal)\b/iu' => "Entendido",
        '/!{2,}/' => "!",
        '/\?{2,}/' => "?"
    ];

    foreach ($replacements as $pattern => $replacement) {
        $text = preg_replace($pattern, $replacement, $text) ?? $text;
    }

    return api_trim_text($text, 1600);
}

function agent_v2_policy_limit_questions(string $reply, int $maxQuestions = 1): string
{
    $text = trim($reply);

    if ($text === "") {
        return "";
    }

    $safeMax = max(1, $maxQuestions);
    $questionCount = substr_count($text, "?");

    if ($questionCount <= $safeMax) {
        return $text;
    }

    $kept = $text;
    $index = -1;

    for ($i = 0; $i < $safeMax; $i++) {
        $index = strpos($kept, "?", $index + 1);

        if (!is_int($index)) {
            return $text;
        }
    }

    $prefix = substr($kept, 0, $index + 1);
    $suffix = substr($kept, $index + 1);
    $suffix = str_replace("?", ".", $suffix);
    $suffix = preg_replace('/\.{2,}/', '.', (string) $suffix) ?? $suffix;
    return trim($prefix . $suffix);
}

function agent_v2_policy_has_scope_disclaimer(string $reply): bool
{
    $text = strtolower(trim($reply));

    if ($text === "") {
        return false;
    }

    return preg_match('/(no\s+desarroll|no\s+constru|no\s+ofrec).*?(app|movil|android|ios)|alternativa\s+web|\bpwa\b/i', $text) === 1;
}

function agent_v2_policy_enforce_pricing_rule(string $reply, array $policy): string
{
    $text = trim($reply);

    if ($text === "" || !(bool) ($policy["noFixedPrices"] ?? true)) {
        return $text;
    }

    if (preg_match('/\b\d+[\d.,]*\s*(eur|euros|€)\b/i', $text) !== 1) {
        return $text;
    }

    $text = preg_replace('/\b\d+[\d.,]*\s*(eur|euros|€)\b/i', 'inversion a definir segun alcance', $text) ?? $text;

    if (preg_match('/detalle\s+real\s+de\s+inversion|cotizacion\s+real/i', $text) !== 1) {
        $text = rtrim($text) . " El detalle real de inversion se define al validar alcance y te lo enviamos por contacto directo.";
    }

    return $text;
}

function agent_v2_policy_enforce_no_guarantees(string $reply, array $policy): string
{
    $text = trim($reply);

    if ($text === "" || !(bool) ($policy["noGuaranteedResults"] ?? true)) {
        return $text;
    }

    $patterns = [
        '/\b(garantizado|garantizada|garantizamos|100\s*%\s*seguro|resultado\s+seguro)\b/iu',
        '/\b(te\s+garantizo|garantizo\s+que|seguro\s+que\s+vas\s+a)\b/iu'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text) === 1) {
            $text = preg_replace($pattern, 'enfoque por fases para reducir riesgo', $text) ?? $text;
        }
    }

    return $text;
}

function agent_v2_policy_enforce_scope_rule(string $reply, array $policy, bool $needsScopeReframe): string
{
    $text = trim($reply);

    if ($text === "") {
        return "";
    }

    $reframeText = api_trim_text((string) ($policy["outOfScopeReframe"] ?? ""), 260);

    if ($reframeText === "") {
        $reframeText = "Ahora mismo no se desarrollan apps moviles nativas, pero podemos cubrir el objetivo con una alternativa web/PWA por fases.";
    }

    $mentionsNativeOffer = preg_match('/(desarrollamos|construimos|hacemos|ofrecemos).{0,50}(app|aplicacion).{0,20}(android|ios|movil\s+nativa)/i', $text) === 1;

    if (!$mentionsNativeOffer) {
        $outOfScope = is_array($policy["outOfScopeServices"] ?? null) ? $policy["outOfScopeServices"] : [];

        foreach ($outOfScope as $service) {
            $needle = strtolower(trim((string) $service));

            if ($needle === "") {
                continue;
            }

            if (strpos(strtolower($text), $needle) !== false && preg_match('/(desarrollamos|hacemos|ofrecemos|incluye|se\s+puede\s+hacer)/i', $text) === 1) {
                $mentionsNativeOffer = true;
                break;
            }
        }
    }

    if ($mentionsNativeOffer) {
        return $reframeText;
    }

    if ($needsScopeReframe && !agent_v2_policy_has_scope_disclaimer($text)) {
        return rtrim($text) . "\n\n" . $reframeText;
    }

    return $text;
}

function agent_v2_policy_enforce_transparency(string $reply, array $policy, string $nextAction, array $missingFields): string
{
    $text = trim($reply);

    if ($text === "" || !(bool) ($policy["transparentWhenUnknown"] ?? true)) {
        return $text;
    }

    if ($nextAction !== "clarify" || empty($missingFields)) {
        return $text;
    }

    if (strpos($text, "?") !== false) {
        return $text;
    }

    $fallback = api_trim_text((string) ($policy["fallbackReply"] ?? ""), 280);

    if ($fallback === "") {
        $fallback = "Con lo que tengo ahora no puedo afinar una recomendacion cerrada sin inventar.";
    }

    $firstField = (string) $missingFields[0];
    return rtrim($fallback) . " " . agent_v2_policy_field_question($firstField);
}

function agent_v2_policy_normalize_suggestions(array $items): array
{
    $clean = [];

    foreach ($items as $item) {
        $text = api_trim_text((string) $item, 90);

        if ($text === "") {
            continue;
        }

        if (!in_array($text, $clean, true)) {
            $clean[] = $text;
        }

        if (count($clean) >= 3) {
            break;
        }
    }

    return $clean;
}

function agent_v2_apply_policy_guard(array $state): array
{
    $config = is_array($state["config"] ?? null) ? $state["config"] : [];
    $policy = agent_v2_motor_policy($config);
    $reply = trim((string) ($state["reply"] ?? ""));
    $nextAction = trim((string) ($state["nextAction"] ?? "explore"));
    $missingFields = is_array($state["missingFields"] ?? null) ? $state["missingFields"] : [];
    $needsScopeReframe = (bool) ($state["needsScopeReframe"] ?? false);
    $suggestions = is_array($state["suggestions"] ?? null) ? $state["suggestions"] : [];

    if ($reply === "") {
        $reply = api_trim_text((string) ($policy["fallbackReply"] ?? ""), 280);
    }

    if ($reply === "") {
        $reply = "Con lo que tengo ahora no puedo responder con precision sin inventar. Te hago una pregunta clave y avanzamos.";
    }

    $reply = agent_v2_policy_refine_tone($reply);
    $reply = agent_v2_policy_enforce_no_guarantees($reply, $policy);
    $reply = agent_v2_policy_enforce_pricing_rule($reply, $policy);
    $reply = agent_v2_policy_enforce_scope_rule($reply, $policy, $needsScopeReframe);
    $reply = agent_v2_policy_enforce_transparency($reply, $policy, $nextAction, $missingFields);
    $reply = agent_v2_policy_limit_questions($reply, (int) ($policy["maxAdvanceQuestions"] ?? 1));
    $reply = api_trim_text($reply, 1600);

    return [
        "reply" => $reply,
        "suggestions" => agent_v2_policy_normalize_suggestions($suggestions)
    ];
}
