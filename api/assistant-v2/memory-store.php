<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "bootstrap.php";

function agent_v2_sanitize_session_id(string $raw): string
{
    $clean = trim($raw);

    if (preg_match("/^[a-zA-Z0-9_-]{6,80}$/", $clean) === 1) {
        return $clean;
    }

    return api_generate_id("sess");
}

function agent_v2_memory_dir(array $config): string
{
    $path = trim((string) ($config["CHAT_V2_MEMORY_PATH"] ?? ""));

    if ($path === "") {
        $path = "../storage/chat-sessions-v2";
    }

    return api_resolve_storage_path($path);
}

function agent_v2_memory_file_path(string $sessionId, array $config): string
{
    return agent_v2_memory_dir($config) . DIRECTORY_SEPARATOR . agent_v2_sanitize_session_id($sessionId) . ".json";
}

function agent_v2_default_memory(string $sessionId): array
{
    $timestamp = gmdate("c");

    return [
        "sessionId" => $sessionId,
        "createdAt" => $timestamp,
        "updatedAt" => $timestamp,
        "turns" => [],
        "lead" => [
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
                "phone" => "",
                "status" => "not_requested"
            ]
        ],
        "conversation" => [
            "closeAttempts" => 0,
            "lastAction" => "explore",
            "lastCloseAttemptAt" => "",
            "quoteSent" => false,
            "quoteSentAt" => ""
        ]
    ];
}

function agent_v2_load_memory(string $sessionId, array $config): array
{
    $safeSessionId = agent_v2_sanitize_session_id($sessionId);
    $memoryPath = agent_v2_memory_file_path($safeSessionId, $config);

    if (!is_file($memoryPath)) {
        return agent_v2_default_memory($safeSessionId);
    }

    $ttl = max(300, min(2592000, (int) ($config['CHAT_SESSION_TTL_SECONDS'] ?? 604800)));
    if (filemtime($memoryPath) < time() - $ttl) return agent_v2_default_memory($safeSessionId);

    $raw = @file_get_contents($memoryPath);

    if (!is_string($raw) || $raw === "") {
        return agent_v2_default_memory($safeSessionId);
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        return agent_v2_default_memory($safeSessionId);
    }

    $defaults = agent_v2_default_memory($safeSessionId);
    $merged = array_replace_recursive($defaults, $decoded);
    $merged["sessionId"] = $safeSessionId;
    return $merged;
}

function agent_v2_save_memory(array $memory, array $config, ?string &$error = null): bool
{
    $sessionId = agent_v2_sanitize_session_id((string) ($memory["sessionId"] ?? ""));
    $memory["sessionId"] = $sessionId;
    $memory["updatedAt"] = gmdate("c");

    $directory = agent_v2_memory_dir($config);

    if (!is_dir($directory)) {
        if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
            $error = "MEMORY_DIR_CREATE_FAILED";
            return false;
        }

        @chmod($directory, 0700);
    }

    $memoryPath = agent_v2_memory_file_path($sessionId, $config);
    $encoded = json_encode($memory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    if (!is_string($encoded) || $encoded === "") {
        $error = "MEMORY_JSON_ENCODE_FAILED";
        return false;
    }

    $temporaryPath = $memoryPath . '.' . bin2hex(random_bytes(8)) . '.tmp';
    $written = @file_put_contents($temporaryPath, $encoded, LOCK_EX);
    if ($written !== false) @chmod($temporaryPath, 0600);

    if ($written !== strlen($encoded) || !@rename($temporaryPath, $memoryPath)) {
        if (is_file($temporaryPath)) @unlink($temporaryPath);
        $error = "MEMORY_WRITE_FAILED";
        return false;
    }

    @chmod($memoryPath, 0600);
    return true;
}

function agent_v2_acquire_memory_lock(string $sessionId, array $config)
{
    try {
        $directory = api_private_directory(agent_v2_memory_dir($config) . '/.locks');
        $path = $directory . '/' . agent_v2_sanitize_session_id($sessionId) . '.lock';
        $lock = @fopen($path, 'c+');
        if (!$lock) throw new RuntimeException('Lock unavailable');
        @chmod($path, 0600);
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            api_error_response(409, 'SESSION_BUSY', 'Hay otra respuesta en curso en esta conversacion.');
        }
        return $lock;
    } catch (Throwable $exception) {
        api_error_response(503, 'MEMORY_UNAVAILABLE', 'La memoria del asistente no esta disponible.');
    }
}

function agent_v2_append_turn(array &$memory, string $role, string $content, int $maxTurns = 16): void
{
    $safeRole = $role === "assistant" ? "assistant" : "user";
    $safeContent = api_trim_text($content, 2200);

    if ($safeContent === "") {
        return;
    }

    if (!isset($memory["turns"]) || !is_array($memory["turns"])) {
        $memory["turns"] = [];
    }

    $memory["turns"][] = [
        "role" => $safeRole,
        "content" => $safeContent,
        "at" => gmdate("c")
    ];

    $safeMax = max(4, $maxTurns);

    if (count($memory["turns"]) > $safeMax) {
        $memory["turns"] = array_slice($memory["turns"], -$safeMax);
    }
}

function agent_v2_missing_brief_fields(array $lead): array
{
    $required = ["objective", "timeline"];
    $missing = [];

    foreach ($required as $field) {
        if (trim((string) ($lead[$field] ?? "")) === "") {
            $missing[] = $field;
        }
    }

    return $missing;
}

function agent_v2_contact_status(array $contact): string
{
    $name = trim((string) ($contact["name"] ?? ""));
    $email = strtolower(trim((string) ($contact["email"] ?? "")));

    if ($name === "" && $email === "") {
        return "not_requested";
    }

    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "invalid_email";
    }

    if (strlen($name) >= 2 && $email !== "") {
        return "provided";
    }

    return "partial";
}

function agent_v2_touch_contact_status(array &$lead): void
{
    $contact = is_array($lead["contact"] ?? null) ? $lead["contact"] : [];
    $status = agent_v2_contact_status($contact);

    $lead["contact"] = [
        "name" => trim((string) ($contact["name"] ?? "")),
        "email" => strtolower(trim((string) ($contact["email"] ?? ""))),
        "phone" => trim((string) ($contact["phone"] ?? "")),
        "status" => $status
    ];
}

function agent_v2_missing_contact_fields(array $lead): array
{
    $contact = is_array($lead["contact"] ?? null) ? $lead["contact"] : [];
    $missing = [];

    if (strlen(trim((string) ($contact["name"] ?? ""))) < 2) {
        $missing[] = "contact.name";
    }

    $email = strtolower(trim((string) ($contact["email"] ?? "")));

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $missing[] = "contact.email";
    }

    return $missing;
}

function agent_v2_contact_is_valid(array $lead): bool
{
    return empty(agent_v2_missing_contact_fields($lead));
}

function agent_v2_memory_summary(array $memory): string
{
    $lead = is_array($memory["lead"] ?? null) ? $memory["lead"] : [];
    $contact = is_array($lead["contact"] ?? null) ? $lead["contact"] : [];
    $parts = [];

    foreach (["projectType", "requestedPlatform", "stackPreference", "collaborationMode", "problem", "objective", "timeline", "budgetHint", "urgency"] as $field) {
        $value = trim((string) ($lead[$field] ?? ""));

        if ($value !== "") {
            $parts[] = $field . "=" . $value;
        }
    }

    if (trim((string) ($contact["name"] ?? "")) !== "") {
        $parts[] = "contact.name=" . (string) $contact["name"];
    }

    if (trim((string) ($contact["email"] ?? "")) !== "") {
        $parts[] = "contact.email=" . (string) $contact["email"];
    }

    if (empty($parts)) {
        return "Sin datos comerciales consolidados.";
    }

    return implode(" | ", $parts);
}
