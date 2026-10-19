<?php

function agent_v2_clean_model_text(string $value, int $maxLength): string
{
    $text = api_trim_text($value, $maxLength);

    if ($text === "") {
        return "";
    }

    $blocked = [
        "texto breve y natural en espanol",
        "opcion 1",
        "opcion 2",
        "opcion 3",
        "string",
        "n/a"
    ];

    if (in_array(strtolower(trim($text)), $blocked, true)) {
        return "";
    }

    return $text;
}

function agent_v2_clean_model_enum(string $value, array $allowed, int $maxLength = 120): string
{
    $text = strtolower(agent_v2_clean_model_text($value, $maxLength));

    if ($text === "") {
        return "";
    }

    return in_array($text, $allowed, true) ? $text : "";
}

function agent_v2_clean_model_email(string $value): string
{
    $email = strtolower(agent_v2_clean_model_text($value, 180));

    if ($email === "") {
        return "";
    }

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : "";
}

function agent_v2_model_payload_defaults(): array
{
    return [
        "reply" => "",
        "leadUpdate" => [
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
        "suggestions" => []
    ];
}

function agent_v2_normalize_model_payload(?array $payload): array
{
    $defaults = agent_v2_model_payload_defaults();

    if (!is_array($payload)) {
        return $defaults;
    }

    $reply = agent_v2_clean_model_text((string) ($payload["reply"] ?? ""), 1600);

    $leadSource = [];

    if (is_array($payload["lead_update"] ?? null)) {
        $leadSource = $payload["lead_update"];
    } elseif (is_array($payload["leadUpdate"] ?? null)) {
        $leadSource = $payload["leadUpdate"];
    } elseif (is_array($payload["lead"] ?? null)) {
        $leadSource = $payload["lead"];
    }

    $contactSource = [];

    if (is_array($payload["contact_update"] ?? null)) {
        $contactSource = $payload["contact_update"];
    } elseif (is_array($payload["contactUpdate"] ?? null)) {
        $contactSource = $payload["contactUpdate"];
    } elseif (is_array($leadSource["contact"] ?? null)) {
        $contactSource = $leadSource["contact"];
    }

    $leadUpdate = [
        "projectType" => agent_v2_clean_model_enum((string) ($leadSource["projectType"] ?? ""), [
            "landing-page",
            "ecommerce-shopify",
            "api-integracion",
            "sistema-interno",
            "web-corporativa",
            "bases-de-datos",
            "mobile-app-request"
        ], 180),
        "requestedPlatform" => agent_v2_clean_model_enum((string) ($leadSource["requestedPlatform"] ?? ""), [
            "android",
            "ios",
            "mobile",
            "web"
        ], 80),
        "stackPreference" => agent_v2_clean_model_text((string) ($leadSource["stackPreference"] ?? ""), 120),
        "collaborationMode" => agent_v2_clean_model_enum((string) ($leadSource["collaborationMode"] ?? ""), [
            "technical-lead",
            "team-collaboration"
        ], 120),
        "problem" => agent_v2_clean_model_text((string) ($leadSource["problem"] ?? ""), 320),
        "objective" => agent_v2_clean_model_text((string) ($leadSource["objective"] ?? ""), 220),
        "timeline" => agent_v2_clean_model_text((string) ($leadSource["timeline"] ?? ""), 120),
        "scopeNotes" => agent_v2_clean_model_text((string) ($leadSource["scopeNotes"] ?? ""), 420),
        "budgetHint" => agent_v2_clean_model_text((string) ($leadSource["budgetHint"] ?? ""), 120),
        "urgency" => agent_v2_clean_model_enum((string) ($leadSource["urgency"] ?? ""), ["alta", "media", "baja"], 80),
        "contact" => [
            "name" => agent_v2_clean_model_text((string) ($contactSource["name"] ?? ""), 120),
            "email" => agent_v2_clean_model_email((string) ($contactSource["email"] ?? "")),
            "phone" => agent_v2_clean_model_text((string) ($contactSource["phone"] ?? ""), 80)
        ]
    ];

    $suggestions = [];

    if (is_array($payload["suggestions"] ?? null)) {
        foreach ($payload["suggestions"] as $item) {
            $text = agent_v2_clean_model_text((string) $item, 90);

            if ($text !== "") {
                $suggestions[] = $text;
            }

            if (count($suggestions) >= 3) {
                break;
            }
        }
    }

    return [
        "reply" => $reply,
        "leadUpdate" => $leadUpdate,
        "suggestions" => $suggestions
    ];
}
