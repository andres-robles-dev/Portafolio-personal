<?php

function agent_v2_request_model_reply(array $config, array $messages, float $temperature = 0.35, int $maxTokens = 900): string
{
    $apiKey = trim((string) ($config["DEEPSEEK_API_KEY"] ?? ""));

    if ($apiKey === "") {
        api_error_response(500, "MISSING_API_KEY", "Falta configurar DEEPSEEK_API_KEY en api/.env o variables de entorno.");
    }

    $model = trim((string) ($config["DEEPSEEK_MODEL"] ?? "llama-3.1-8b-instant"));
    $base = rtrim(trim((string) ($config["DEEPSEEK_API_BASE"] ?? "https://api.groq.com/openai/v1")), "/");
    $timeoutSeconds = max(5, (int) ($config["DEEPSEEK_TIMEOUT_SECONDS"] ?? 30));

    $upstream = api_post_json(
        $base . "/chat/completions",
        [
            "model" => $model,
            "messages" => $messages,
            "temperature" => max(0.0, min(1.0, $temperature)),
            "max_tokens" => max(250, $maxTokens),
            "stream" => false
        ],
        [
            "Authorization: Bearer " . $apiKey
        ],
        $timeoutSeconds
    );

    if (!empty($upstream["error"])) {
        api_error_response(502, "UPSTREAM_CONNECTION_ERROR", "No se pudo conectar con el proveedor IA.");
    }

    $status = (int) ($upstream["status"] ?? 0);

    if ($status < 200 || $status >= 300) {
        api_error_response(502, "UPSTREAM_HTTP_ERROR", "El proveedor IA devolvio un estado no exitoso.", [
            "upstreamStatus" => $status
        ]);
    }

    $decoded = json_decode((string) ($upstream["body"] ?? ""), true);
    $reply = api_trim_text((string) ($decoded["choices"][0]["message"]["content"] ?? ""), 8000);

    if ($reply === "") {
        api_error_response(502, "EMPTY_UPSTREAM_REPLY", "El proveedor IA no devolvio contenido util.");
    }

    return $reply;
}

function agent_v2_parse_json_object(string $raw): ?array
{
    $text = trim($raw);

    if ($text === "") {
        return null;
    }

    $decoded = json_decode($text, true);

    if (is_array($decoded)) {
        return $decoded;
    }

    $firstBrace = strpos($text, "{");
    $lastBrace = strrpos($text, "}");

    if (!is_int($firstBrace) || !is_int($lastBrace) || $lastBrace <= $firstBrace) {
        return null;
    }

    $slice = substr($text, $firstBrace, $lastBrace - $firstBrace + 1);
    $decodedSlice = json_decode((string) $slice, true);

    if (!is_array($decodedSlice)) {
        return null;
    }

    return $decodedSlice;
}
