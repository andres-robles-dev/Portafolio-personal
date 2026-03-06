<?php

require __DIR__ . DIRECTORY_SEPARATOR . "bootstrap.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    api_error_response(405, "METHOD_NOT_ALLOWED", "Solo se permite GET para este endpoint.");
}

$config = api_load_config();
$expectedToken = trim((string) ($config["HEALTHCHECK_TOKEN"] ?? ""));

if ($expectedToken === "") {
    api_error_response(403, "HEALTHCHECK_DISABLED", "Configura HEALTHCHECK_TOKEN en api/.env para habilitar este endpoint.");
}

$incomingToken = trim((string) ($_GET["token"] ?? $_SERVER["HTTP_X_HEALTH_TOKEN"] ?? ""));

if (!hash_equals($expectedToken, $incomingToken)) {
    api_error_response(403, "FORBIDDEN", "Token de healthcheck invalido.");
}

$httpsOn = (
    (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "" && strtolower((string) $_SERVER["HTTPS"]) !== "off")
    || ((string) ($_SERVER["SERVER_PORT"] ?? "") === "443")
    || (strtolower((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")) === "https")
);

$displayErrorsRaw = (string) ini_get("display_errors");
$displayErrorsOff = in_array(strtolower($displayErrorsRaw), ["0", "off", ""], true);

$storagePath = api_resolve_storage_path((string) ($config["CONTACT_STORAGE_PATH"] ?? "../storage/contacts.ndjson"));
$storageDir = dirname($storagePath);
$storageDirExists = is_dir($storageDir);
$storageDirWritable = $storageDirExists ? is_writable($storageDir) : false;

$chatMemoryConfigKey = "CHAT_V2_MEMORY_PATH";
$chatMemoryDefaultPath = "../storage/chat-sessions-v2";
$chatMemoryPath = api_resolve_storage_path((string) ($config["CHAT_V2_MEMORY_PATH"] ?? $chatMemoryDefaultPath));
$chatMemoryDirExists = is_dir($chatMemoryPath);
$chatMemoryDirWritable = $chatMemoryDirExists ? is_writable($chatMemoryPath) : false;

$probeWritable = false;
$probeError = null;

if ($storageDirExists && $storageDirWritable) {
    $probePath = $storageDir . DIRECTORY_SEPARATOR . ".write_probe";
    $write = @file_put_contents($probePath, "probe=" . date("c") . PHP_EOL, LOCK_EX);

    if ($write === false) {
        $probeError = "WRITE_PROBE_FAILED";
    } else {
        $probeWritable = true;
        @unlink($probePath);
    }
} else {
    $probeError = "STORAGE_DIR_NOT_WRITABLE";
}

$memoryProbeWritable = false;
$memoryProbeError = null;

if ($chatMemoryDirExists && $chatMemoryDirWritable) {
    $memoryProbePath = $chatMemoryPath . DIRECTORY_SEPARATOR . ".memory_write_probe";
    $memoryWrite = @file_put_contents($memoryProbePath, "probe=" . date("c") . PHP_EOL, LOCK_EX);

    if ($memoryWrite === false) {
        $memoryProbeError = "MEMORY_WRITE_PROBE_FAILED";
    } else {
        $memoryProbeWritable = true;
        @unlink($memoryProbePath);
    }
} else {
    $memoryProbeError = "CHAT_MEMORY_DIR_NOT_WRITABLE";
}

$checks = [
    "https" => $httpsOn,
    "displayErrorsOff" => $displayErrorsOff,
    "storageDirExists" => $storageDirExists,
    "storageDirWritable" => $storageDirWritable,
    "storageProbeWritable" => $probeWritable,
    "chatMemoryDirExists" => $chatMemoryDirExists,
    "chatMemoryDirWritable" => $chatMemoryDirWritable,
    "chatMemoryProbeWritable" => $memoryProbeWritable,
    "deepseekKeyConfigured" => trim((string) ($config["DEEPSEEK_API_KEY"] ?? "")) !== ""
];

$ok = true;

foreach ($checks as $value) {
    if ($value !== true) {
        $ok = false;
        break;
    }
}

api_json_response($ok ? 200 : 503, [
    "ok" => $ok,
    "checks" => $checks,
    "meta" => [
        "phpVersion" => PHP_VERSION,
        "chatMemoryConfigKey" => $chatMemoryConfigKey,
        "storagePath" => $storagePath,
        "chatMemoryPath" => $chatMemoryPath,
        "probeError" => $probeError,
        "memoryProbeError" => $memoryProbeError,
        "displayErrorsRaw" => $displayErrorsRaw
    ]
]);
