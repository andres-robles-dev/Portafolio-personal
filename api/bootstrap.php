<?php

ini_set('display_errors', '0');
require_once __DIR__ . '/security.php';

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

    if (!$envLoaded && getenv('APP_LOAD_ENV') !== '0') {
        api_load_env_file(api_env_value('API_ENV_FILE', __DIR__ . DIRECTORY_SEPARATOR . '.env'));
        $envLoaded = true;
    }

    $defaults = [
        "APP_MODE" => "live",
        "CHAT_GLOBAL_DAILY_MAX" => 200,
        "CONTACT_GLOBAL_HOURLY_MAX" => 100,
        "CHAT_SESSION_TTL_SECONDS" => 604800,
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
        "CHAT_KNOWLEDGE_PATH" => "./knowledge/motor.json",
        "CHAT_RETRIEVAL_BLOCKS" => 4,
        "CHAT_QUOTE_NOTIFY_EMAIL" => "",
        "CHAT_QUOTE_STORAGE_PATH" => "../storage/quotes.ndjson",
        "PUBLIC_CONTACT_EMAIL" => "",
        "PUBLIC_CONTACT_WHATSAPP" => "",
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
    $config['APP_MODE'] = api_env_value('APP_MODE', 'live') === 'demo' ? 'demo' : 'live';
    foreach (['CHAT_GLOBAL_DAILY_MAX', 'CONTACT_GLOBAL_HOURLY_MAX', 'CHAT_SESSION_TTL_SECONDS'] as $key) {
        $config[$key] = max(1, min(2592000, (int) api_env_value($key, (string) $defaults[$key])));
    }
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
    $config["PUBLIC_CONTACT_EMAIL"] = trim(api_env_value("PUBLIC_CONTACT_EMAIL", $defaults["PUBLIC_CONTACT_EMAIL"]));
    $config["PUBLIC_CONTACT_WHATSAPP"] = trim(api_env_value("PUBLIC_CONTACT_WHATSAPP", $defaults["PUBLIC_CONTACT_WHATSAPP"]));
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
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        api_error_response(415, 'UNSUPPORTED_MEDIA_TYPE', 'Se requiere Content-Type application/json.');
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBytes) {
        api_error_response(413, 'PAYLOAD_TOO_LARGE', 'El payload supera el tamano permitido.');
    }
    $raw = (string) file_get_contents('php://input', false, null, 0, $maxBytes + 1);

    if ($raw === "") {
        api_error_response(400, "EMPTY_BODY", "El cuerpo de la peticion esta vacio.");
    }

    if (strlen($raw) > $maxBytes) {
        api_error_response(413, "PAYLOAD_TOO_LARGE", "El payload supera el tamano permitido.");
    }

    $object = json_decode($raw, false, 32);
    $decoded = json_decode($raw, true, 32);

    if (!is_object($object) || !is_array($decoded)) {
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

        if (!in_array($entry['role'] ?? '', ['user', 'assistant'], true) || !is_string($entry['content'] ?? null)) {
            api_error_response(422, 'VALIDATION_ERROR', 'Historial invalido.');
        }
        $role = $entry['role'];
        $content = api_trim_text($entry['content'], 1000);

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
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trusted = array_filter(array_map('trim', explode(',', api_env_value('TRUSTED_PROXIES'))));
    if (in_array($remote, $trusted, true)) {
        $forwarded = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]);
        if (filter_var($forwarded, FILTER_VALIDATE_IP)) return $forwarded;
    }
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

function api_rate_limit_allow(string $bucketKey, int $maxRequests, int $windowSeconds): bool
{
    $safeWindow = max(1, $windowSeconds);
    $safeMax = max(1, min(5000, $maxRequests));
    $directory = api_env_value('RATE_LIMIT_DIR', sys_get_temp_dir() . '/portfolio-rate-' . substr(hash('sha256', __DIR__), 0, 16));
    $root = strtolower(str_replace('\\', '/', dirname(__DIR__))) . '/';
    $handle = null;
    try {
        $directory = api_private_directory($directory);
        if (str_starts_with(strtolower(str_replace('\\', '/', $directory)) . '/', $root)) throw new RuntimeException('Public counters');
        $file = $directory . '/' . hash('sha256', $bucketKey) . '.json';
        $handle = @fopen($file, 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('Counters unavailable');
        @chmod($file, 0600);
        $now = time();
        $windowStart = $now - $safeWindow;
        $events = [];
        $raw = stream_get_contents($handle, 131073);
        if (strlen($raw) > 131072) throw new RuntimeException('Invalid counter size');
        if ($raw !== '') {
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) throw new RuntimeException('Invalid counter');
            foreach ($decoded as $timestamp) {
                if (is_int($timestamp) && $timestamp >= $windowStart) {
                    $events[] = $timestamp;
                }
            }
        }
        if (count($events) >= $safeMax) return false;
        $events[] = $now;
        $encoded = json_encode($events, JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) throw new RuntimeException('Counter write failed');
        return true;
    } catch (Throwable $exception) {
        api_error_response(503, 'RATE_LIMIT_UNAVAILABLE', 'El control de uso no esta disponible temporalmente.');
    } finally {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

function api_post_json(string $url, array $payload, array $headers = [], int $timeoutSeconds = 30): array
{
    if (parse_url($url, PHP_URL_SCHEME) !== 'https') return ['status' => 0, 'body' => '', 'error' => 'INVALID_UPSTREAM_URL', 'errno' => 0];
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $effectiveHeaders = array_merge([
        "Content-Type: application/json"
    ], $headers);

    if (function_exists("curl_init")) {
        $handle = curl_init($url);

        try {
            $tls = api_tls_options();
        } catch (Throwable $exception) {
            curl_close($handle);
            return ['status' => 0, 'body' => '', 'error' => 'TLS_CONFIG_ERROR', 'errno' => 60];
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $effectiveHeaders,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_CONNECTTIMEOUT => max(1, min(15, $timeoutSeconds)),
            CURLOPT_FOLLOWLOCATION => false
        ] + $tls);

        $body = curl_exec($handle);
        $errno = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return [
            "status" => $status,
            "body" => is_string($body) ? $body : "",
            "error" => $errno !== 0 ? 'TRANSPORT_ERROR' : null,
            'errno' => $errno
        ];
    }

    return ['status' => 0, 'body' => '', 'error' => 'CURL_UNAVAILABLE', 'errno' => 0];
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
    $trimmed = str_replace('\\', '/', trim($path));

    if ($trimmed === "") {
        return __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "contacts.ndjson";
    }

    if (preg_match('~^(?:[A-Za-z]:/|/)~', $trimmed) === 1) {
        return $trimmed;
    }

    return __DIR__ . DIRECTORY_SEPARATOR . $trimmed;
}

function api_append_json_line(string $filePath, array $payload, ?string &$error = null): bool
{
    $dir = dirname($filePath);

    if (!is_dir($dir)) {
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            $error = "NO_STORAGE_DIR";
            return false;
        }

        @chmod($dir, 0700);
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

    @chmod($filePath, 0600);

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

    $safeSubject = api_safe_mail_subject($subject);
    $safeBody = trim($body);

    if ($safeSubject === "" || $safeBody === "") {
        return false;
    }

    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, $safeSubject, $safeBody, $headers);
}

function api_safe_mail_subject(string $subject): string
{
    return api_trim_text((string) preg_replace('/[\r\n\x00]+/', ' ', $subject), 180);
}
