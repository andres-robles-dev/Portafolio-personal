<?php

if (function_exists("header_remove")) {
    @header_remove("X-Powered-By");
}

function api_json_response(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store");
    header("X-Content-Type-Options: nosniff");

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error_response(int $statusCode, string $code, string $message, array $extra = []): never
{
    api_json_response($statusCode, array_merge([
        "error" => [
            "code" => $code,
            "message" => $message
        ]
    ], $extra));
}

function api_load_env_file(string $filePath): void
{
    if (!is_file($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);

        if ($trimmed === "" || strpos($trimmed, "#") === 0) {
            continue;
        }

        $separator = strpos($trimmed, "=");

        if ($separator === false) {
            continue;
        }

        $key = trim(substr($trimmed, 0, $separator));
        $value = trim(substr($trimmed, $separator + 1));

        if ($key === "") {
            continue;
        }

        $first = substr($value, 0, 1);
        $last = substr($value, -1);

        if ((($first === '"') && ($last === '"')) || (($first === "'") && ($last === "'"))) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . "=" . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function api_env_value(string $key, string $default = ""): string
{
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    return (string) $value;
}

function api_load_config(): array
{
    static $config = null;
    static $envLoaded = false;

    if (is_array($config)) {
        return $config;
    }

    if (!$envLoaded) {
        api_load_env_file(__DIR__ . DIRECTORY_SEPARATOR . ".env");
        $envLoaded = true;
    }

    $defaults = [
        "DEEPSEEK_API_KEY" => "",
        "DEEPSEEK_MODEL" => "llama-3.1-8b-instant",
        "DEEPSEEK_API_BASE" => "https://api.groq.com/openai/v1",
        "DEEPSEEK_TIMEOUT_SECONDS" => 30,
        "CHAT_RATE_LIMIT_MAX" => 12,
        "CHAT_RATE_LIMIT_WINDOW_SECONDS" => 60,
        "CHAT_HISTORY_MAX_ITEMS" => 10,
        "CHAT_MEMORY_MAX_TURNS" => 12,
        "CHAT_V2_MEMORY_PATH" => "../storage/chat-sessions-v2",
        "CHAT_V2_READINESS_THRESHOLD" => 60,
        "CHAT_V2_CLOSE_SOFT_ATTEMPTS" => 2,
        "CHAT_V2_TEMPERATURE" => "0.35",
        "CHAT_V2_MAX_TOKENS" => 900,
        "CHAT_KNOWLEDGE_PATH" => "./knowledge",
        "CHAT_RETRIEVAL_BLOCKS" => 4,
        "CHAT_QUOTE_NOTIFY_EMAIL" => "drezyroble09@gmail.com",
        "CHAT_QUOTE_STORAGE_PATH" => "../storage/quotes.ndjson",
        "CONTACT_STORAGE_PATH" => "../storage/contacts.ndjson",
        "CONTACT_NOTIFY_EMAIL" => "",
        "CONTACT_RATE_LIMIT_MAX" => 3,
        "CONTACT_RATE_LIMIT_WINDOW_SECONDS" => 600,
        "CONTACT_EMAIL_DAILY_MAX" => 5,
        "CONTACT_EMAIL_DAILY_WINDOW_SECONDS" => 86400,
        "CONTACT_MIN_FILL_SECONDS" => 4,
        "CONTACT_HONEYPOT_FIELD" => "website",
        "HEALTHCHECK_TOKEN" => ""
    ];

    $config = $defaults;
    $config["DEEPSEEK_API_KEY"] = trim(api_env_value("DEEPSEEK_API_KEY", $defaults["DEEPSEEK_API_KEY"]));
    $config["DEEPSEEK_MODEL"] = trim(api_env_value("DEEPSEEK_MODEL", $defaults["DEEPSEEK_MODEL"]));
    $config["DEEPSEEK_API_BASE"] = trim(api_env_value("DEEPSEEK_API_BASE", $defaults["DEEPSEEK_API_BASE"]));
    $config["DEEPSEEK_TIMEOUT_SECONDS"] = (int) api_env_value("DEEPSEEK_TIMEOUT_SECONDS", (string) $defaults["DEEPSEEK_TIMEOUT_SECONDS"]);
    $config["CHAT_RATE_LIMIT_MAX"] = (int) api_env_value("CHAT_RATE_LIMIT_MAX", (string) $defaults["CHAT_RATE_LIMIT_MAX"]);
    $config["CHAT_RATE_LIMIT_WINDOW_SECONDS"] = (int) api_env_value("CHAT_RATE_LIMIT_WINDOW_SECONDS", (string) $defaults["CHAT_RATE_LIMIT_WINDOW_SECONDS"]);
    $config["CHAT_HISTORY_MAX_ITEMS"] = (int) api_env_value("CHAT_HISTORY_MAX_ITEMS", (string) $defaults["CHAT_HISTORY_MAX_ITEMS"]);
    $config["CHAT_MEMORY_MAX_TURNS"] = (int) api_env_value("CHAT_MEMORY_MAX_TURNS", (string) $defaults["CHAT_MEMORY_MAX_TURNS"]);
    $config["CHAT_V2_MEMORY_PATH"] = trim(api_env_value("CHAT_V2_MEMORY_PATH", $defaults["CHAT_V2_MEMORY_PATH"]));
    $config["CHAT_V2_READINESS_THRESHOLD"] = (int) api_env_value("CHAT_V2_READINESS_THRESHOLD", (string) $defaults["CHAT_V2_READINESS_THRESHOLD"]);
    $config["CHAT_V2_CLOSE_SOFT_ATTEMPTS"] = (int) api_env_value("CHAT_V2_CLOSE_SOFT_ATTEMPTS", (string) $defaults["CHAT_V2_CLOSE_SOFT_ATTEMPTS"]);
    $config["CHAT_V2_TEMPERATURE"] = (float) api_env_value("CHAT_V2_TEMPERATURE", (string) $defaults["CHAT_V2_TEMPERATURE"]);
    $config["CHAT_V2_MAX_TOKENS"] = (int) api_env_value("CHAT_V2_MAX_TOKENS", (string) $defaults["CHAT_V2_MAX_TOKENS"]);
    $config["CHAT_KNOWLEDGE_PATH"] = trim(api_env_value("CHAT_KNOWLEDGE_PATH", $defaults["CHAT_KNOWLEDGE_PATH"]));
    $config["CHAT_RETRIEVAL_BLOCKS"] = (int) api_env_value("CHAT_RETRIEVAL_BLOCKS", (string) $defaults["CHAT_RETRIEVAL_BLOCKS"]);
    $config["CHAT_QUOTE_NOTIFY_EMAIL"] = trim(api_env_value("CHAT_QUOTE_NOTIFY_EMAIL", $defaults["CHAT_QUOTE_NOTIFY_EMAIL"]));
    $config["CHAT_QUOTE_STORAGE_PATH"] = trim(api_env_value("CHAT_QUOTE_STORAGE_PATH", $defaults["CHAT_QUOTE_STORAGE_PATH"]));
    $config["CONTACT_STORAGE_PATH"] = trim(api_env_value("CONTACT_STORAGE_PATH", $defaults["CONTACT_STORAGE_PATH"]));
    $config["CONTACT_NOTIFY_EMAIL"] = trim(api_env_value("CONTACT_NOTIFY_EMAIL", $defaults["CONTACT_NOTIFY_EMAIL"]));
    $config["CONTACT_RATE_LIMIT_MAX"] = (int) api_env_value("CONTACT_RATE_LIMIT_MAX", (string) $defaults["CONTACT_RATE_LIMIT_MAX"]);
    $config["CONTACT_RATE_LIMIT_WINDOW_SECONDS"] = (int) api_env_value("CONTACT_RATE_LIMIT_WINDOW_SECONDS", (string) $defaults["CONTACT_RATE_LIMIT_WINDOW_SECONDS"]);
    $config["CONTACT_EMAIL_DAILY_MAX"] = (int) api_env_value("CONTACT_EMAIL_DAILY_MAX", (string) $defaults["CONTACT_EMAIL_DAILY_MAX"]);
    $config["CONTACT_EMAIL_DAILY_WINDOW_SECONDS"] = (int) api_env_value("CONTACT_EMAIL_DAILY_WINDOW_SECONDS", (string) $defaults["CONTACT_EMAIL_DAILY_WINDOW_SECONDS"]);
    $config["CONTACT_MIN_FILL_SECONDS"] = (int) api_env_value("CONTACT_MIN_FILL_SECONDS", (string) $defaults["CONTACT_MIN_FILL_SECONDS"]);
    $config["CONTACT_HONEYPOT_FIELD"] = trim(api_env_value("CONTACT_HONEYPOT_FIELD", $defaults["CONTACT_HONEYPOT_FIELD"]));
    $config["HEALTHCHECK_TOKEN"] = trim(api_env_value("HEALTHCHECK_TOKEN", $defaults["HEALTHCHECK_TOKEN"]));

    return $config;
}

function api_get_json_body(int $maxBytes = 65536): array
{
    $raw = (string) file_get_contents("php://input");

    if ($raw === "") {
        api_error_response(400, "EMPTY_BODY", "El cuerpo de la peticion esta vacio.");
    }

    if (strlen($raw) > $maxBytes) {
        api_error_response(413, "PAYLOAD_TOO_LARGE", "El payload supera el tamano permitido.");
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        api_error_response(400, "INVALID_JSON", "El cuerpo debe ser JSON valido.");
    }

    return $decoded;
}

function api_trim_text(string $value, int $maxLength): string
{
    $trimmed = trim($value);

    if ($trimmed === "") {
        return "";
    }

    if (function_exists("mb_substr")) {
        return mb_substr($trimmed, 0, $maxLength, "UTF-8");
    }

    return substr($trimmed, 0, $maxLength);
}

function api_normalize_history($history, int $maxItems = 10): array
{
    if (!is_array($history)) {
        return [];
    }

    $clean = [];

    foreach ($history as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $role = isset($entry["role"]) && $entry["role"] === "assistant" ? "assistant" : "user";
        $content = api_trim_text((string) ($entry["content"] ?? ""), 1000);

        if ($content === "") {
            continue;
        }

        $clean[] = [
            "role" => $role,
            "content" => $content
        ];
    }

    if (count($clean) <= $maxItems) {
        return $clean;
    }

    return array_slice($clean, -$maxItems);
}

function api_client_ip(): string
{
    $ip = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";

    if (strpos($ip, ",") !== false) {
        $parts = explode(",", $ip);
        $ip = trim($parts[0]);
    }

    return trim($ip) !== "" ? trim($ip) : "0.0.0.0";
}

function api_rate_limit_allow(string $bucketKey, int $maxRequests, int $windowSeconds): bool
{
    $safeWindow = max(1, $windowSeconds);
    $safeMax = max(1, $maxRequests);
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "andro_api_rl_" . sha1($bucketKey) . ".json";
    $now = time();
    $windowStart = $now - $safeWindow;
    $events = [];

    if (is_file($file)) {
        $raw = (string) file_get_contents($file);
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            foreach ($decoded as $timestamp) {
                if (is_int($timestamp) && $timestamp >= $windowStart) {
                    $events[] = $timestamp;
                }
            }
        }
    }

    if (count($events) >= $safeMax) {
        file_put_contents($file, json_encode($events), LOCK_EX);
        return false;
    }

    $events[] = $now;
    file_put_contents($file, json_encode($events), LOCK_EX);
    return true;
}

function api_post_json(string $url, array $payload, array $headers = [], int $timeoutSeconds = 30): array
{
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $effectiveHeaders = array_merge([
        "Content-Type: application/json"
    ], $headers);

    if (function_exists("curl_init")) {
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $effectiveHeaders,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_CONNECTTIMEOUT => max(1, min(15, $timeoutSeconds)),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $body = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return [
            "status" => $status,
            "body" => is_string($body) ? $body : "",
            "error" => $error !== "" ? $error : null
        ];
    }

    $context = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => implode("\r\n", $effectiveHeaders),
            "content" => $jsonPayload,
            "timeout" => max(1, $timeoutSeconds),
            "ignore_errors" => true
        ]
    ]);

    $body = @file_get_contents($url, false, $context);
    $status = 0;

    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        if (preg_match("#HTTP/[0-9.]+\s+(\d{3})#", $http_response_header[0], $matches)) {
            $status = (int) $matches[1];
        }
    }

    return [
        "status" => $status,
        "body" => is_string($body) ? $body : "",
        "error" => null
    ];
}

function api_generate_id(string $prefix = "id"): string
{
    try {
        return $prefix . "_" . bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        return $prefix . "_" . dechex(time()) . "_" . substr(md5(uniqid("", true)), 0, 10);
    }
}

function api_resolve_storage_path(string $path): string
{
    $trimmed = trim($path);

    if ($trimmed === "") {
        return __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "contacts.ndjson";
    }

    if (preg_match("#^([A-Za-z]:[\\/]|/)#", $trimmed) === 1) {
        return $trimmed;
    }

    return __DIR__ . DIRECTORY_SEPARATOR . $trimmed;
}

function api_append_json_line(string $filePath, array $payload, ?string &$error = null): bool
{
    $dir = dirname($filePath);

    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            $error = "NO_STORAGE_DIR";
            return false;
        }

        @chmod($dir, 0775);
    }

    $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($line) || $line === "") {
        $error = "JSON_ENCODE_ERROR";
        return false;
    }

    $written = @file_put_contents($filePath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

    if ($written === false) {
        $error = "WRITE_FAILED";
        return false;
    }

    @chmod($filePath, 0664);

    return true;
}

function api_try_send_contact_mail(string $recipientEmail, array $record): bool
{
    $subject = "Nuevo lead web - " . ($record["inquiryType"] ?? "sin-tipo");

    $lines = [
        "Has recibido una nueva solicitud desde el formulario web.",
        "",
        "ID: " . ($record["id"] ?? ""),
        "Fecha: " . ($record["createdAt"] ?? ""),
        "Nombre: " . ($record["name"] ?? ""),
        "Email: " . ($record["email"] ?? ""),
        "Empresa: " . ($record["company"] ?? ""),
        "Tipo: " . ($record["inquiryType"] ?? ""),
        "Origen: " . ($record["source"] ?? ""),
        "IP: " . ($record["ip"] ?? ""),
        "",
        "Mensaje:",
        (string) ($record["message"] ?? "")
    ];

    $body = implode("\n", $lines);
    return api_try_send_plain_mail($recipientEmail, $subject, $body);
}

function api_try_send_plain_mail(string $recipientEmail, string $subject, string $body): bool
{
    if (!function_exists("mail")) {
        return false;
    }

    $to = trim($recipientEmail);

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $safeSubject = trim($subject);
    $safeBody = trim($body);

    if ($safeSubject === "" || $safeBody === "") {
        return false;
    }

    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, $safeSubject, $safeBody, $headers);
}
