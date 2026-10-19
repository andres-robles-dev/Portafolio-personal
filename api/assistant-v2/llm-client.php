<?php

function agent_v2_request_model_reply(array $config, array $messages, float $temperature = 0.35, int $maxTokens = 900, ?callable $transport = null): string
{
    $apiKey = trim((string) ($config["DEEPSEEK_API_KEY"] ?? ""));

    if ($apiKey === "") {
        api_error_response(503, "MISSING_API_KEY", "El asistente IA no esta configurado. Puedes explorar la web o utilizar el formulario.");
    }

    $model = trim((string) ($config["DEEPSEEK_MODEL"] ?? "llama-3.1-8b-instant"));
    $base = rtrim(trim((string) ($config["DEEPSEEK_API_BASE"] ?? "https://api.groq.com/openai/v1")), "/");
    $timeoutSeconds = max(5, (int) ($config["DEEPSEEK_TIMEOUT_SECONDS"] ?? 30));

    $request = $transport ?? 'api_post_json';
    $upstream = $request(
        $base . "/chat/completions",
        [
            "model" => $model,
            "messages" => $messages,
            "temperature" => max(0.0, min(1.0, $temperature)),
            "max_tokens" => max(250, min(1200, $maxTokens)),
            "stream" => false
        ],
        [
            "Authorization: Bearer " . $apiKey
        ],
        $timeoutSeconds
    );

    if (!empty($upstream["error"])) {
        if (in_array((int) ($upstream['errno'] ?? 0), [51, 58, 60, 77, 90, 91], true)) {
            api_error_response(502, 'UPSTREAM_TLS_ERROR', 'PHP no puede verificar el certificado HTTPS del proveedor IA.');
        }
        if (($upstream['errno'] ?? 0) === 28) api_error_response(504, 'UPSTREAM_TIMEOUT', 'El proveedor IA tardo demasiado en responder.');
        api_error_response(502, "UPSTREAM_CONNECTION_ERROR", "No se pudo conectar con el proveedor IA.");
    }

    $status = (int) ($upstream["status"] ?? 0);
    if ($status === 401 || $status === 403) api_error_response(502, 'UPSTREAM_AUTH_ERROR', 'El proveedor IA rechazo las credenciales o permisos del servidor.');
    if ($status === 429) {
        header('Retry-After: 60');
        api_error_response(429, 'UPSTREAM_RATE_LIMIT', 'El proveedor IA alcanzo un limite de uso. Intenta mas tarde.');
    }

    if ($status < 200 || $status >= 300) {
        api_error_response(502, "UPSTREAM_HTTP_ERROR", "El proveedor IA devolvio un estado no exitoso.", [
            "upstreamStatus" => $status
        ]);
    }

    $decoded = json_decode((string) ($upstream["body"] ?? ""), true);
    $content = $decoded['choices'][0]['message']['content'] ?? null;
    $reply = is_string($content) ? api_trim_text($content, 8000) : '';

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
