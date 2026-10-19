<?php

require __DIR__ . DIRECTORY_SEPARATOR . "bootstrap.php";

api_require_post();

$config = api_load_config();

if (!api_rate_limit_allow(
    "contact:" . api_client_ip(),
    (int) $config["CONTACT_RATE_LIMIT_MAX"],
    (int) $config["CONTACT_RATE_LIMIT_WINDOW_SECONDS"]
)) {
    api_error_response(429, "RATE_LIMIT", "Has alcanzado el limite de solicitudes. Intenta de nuevo en unos segundos.");
}

$body = api_get_json_body();
api_validate_text_fields($body, ['name' => 480, 'email' => 180, 'company' => 720, 'inquiryType' => 80, 'message' => 16000, 'source' => 80, 'createdAt' => 40, 'website' => 180]);
foreach (['formStartedAt', 'submittedAt'] as $field) {
    if (isset($body[$field]) && !is_int($body[$field]) && !(is_string($body[$field]) && ctype_digit($body[$field]))) {
        api_error_response(422, 'VALIDATION_ERROR', 'Marca temporal invalida.');
    }
}
if (!api_rate_limit_allow('contact-global', (int) $config['CONTACT_GLOBAL_HOURLY_MAX'], 3600)) {
    api_error_response(429, 'RATE_LIMIT', 'Se alcanzo el limite global de solicitudes.');
}

$honeypotField = api_trim_text((string) ($config["CONTACT_HONEYPOT_FIELD"] ?? "website"), 40);

if ($honeypotField === "") {
    $honeypotField = "website";
}

api_validate_text_fields($body, [$honeypotField => 180]);
$honeypotValue = api_trim_text((string) ($body[$honeypotField] ?? ""), 180);

if ($honeypotValue !== "") {
    api_json_response(200, [
        "status" => "ok",
        "stored" => false,
        "screened" => true
    ]);
}

$formStartedAtMs = (int) ($body["formStartedAt"] ?? 0);
$submittedAtMs = (int) ($body["submittedAt"] ?? round(microtime(true) * 1000));
$minFillMs = max(0, (int) ($config["CONTACT_MIN_FILL_SECONDS"] ?? 0)) * 1000;

if ($minFillMs > 0 && $formStartedAtMs > 0 && $submittedAtMs > 0) {
    $elapsedMs = $submittedAtMs - $formStartedAtMs;

    if ($elapsedMs > 0 && $elapsedMs < $minFillMs) {
        api_json_response(200, [
            "status" => "ok",
            "stored" => false,
            "screened" => true
        ]);
    }
}

$payload = [
    "name" => api_trim_text((string) ($body["name"] ?? ""), 120),
    "email" => api_trim_text((string) ($body["email"] ?? ""), 180),
    "company" => api_trim_text((string) ($body["company"] ?? ""), 180),
    "inquiryType" => api_trim_text((string) ($body["inquiryType"] ?? ""), 80),
    "message" => api_trim_text((string) ($body["message"] ?? ""), 4000),
    "source" => api_trim_text((string) ($body["source"] ?? "portfolio-web"), 80),
    "createdAt" => gmdate("c")
];

$errors = [];

if (strlen($payload["name"]) < 2) {
    $errors["name"] = "Nombre invalido.";
}

if (!filter_var($payload["email"], FILTER_VALIDATE_EMAIL)) {
    $errors["email"] = "Email invalido.";
}

if ($payload["inquiryType"] === "") {
    $errors["inquiryType"] = "Tipo de solicitud requerido.";
}

if (strlen($payload["message"]) < 20) {
    $errors["message"] = "El mensaje debe tener al menos 20 caracteres.";
}

if (!empty($errors)) {
    api_error_response(422, "VALIDATION_ERROR", "Revisa los campos enviados.", [
        "fields" => $errors
    ]);
}

if ($config['APP_MODE'] === 'demo') {
    api_json_response(200, ['status' => 'demo', 'stored' => false, 'notified' => false]);
}

$emailBucket = strtolower($payload["email"]);

if ($emailBucket !== "" && !api_rate_limit_allow(
    "contact-email:" . sha1($emailBucket),
    (int) ($config["CONTACT_EMAIL_DAILY_MAX"] ?? 5),
    (int) ($config["CONTACT_EMAIL_DAILY_WINDOW_SECONDS"] ?? 86400)
)) {
    api_error_response(429, "RATE_LIMIT_EMAIL", "Este email alcanzo el limite de envios temporalmente.");
}

$record = [
    "id" => api_generate_id("lead"),
    "createdAt" => $payload["createdAt"],
    "name" => $payload["name"],
    "email" => $payload["email"],
    "company" => $payload["company"],
    "inquiryType" => $payload["inquiryType"],
    "message" => $payload["message"],
    "source" => $payload["source"],
    "ip" => api_client_ip(),
    "userAgent" => api_trim_text((string) ($_SERVER["HTTP_USER_AGENT"] ?? ""), 300)
];

$storagePath = api_resolve_storage_path((string) ($config["CONTACT_STORAGE_PATH"] ?? "../storage/contacts.ndjson"));
$storageError = null;

if (!api_append_json_line($storagePath, $record, $storageError)) {
    api_error_response(500, "CONTACT_STORAGE_ERROR", "No se pudo guardar la solicitud en el backend.", [
        "reason" => $storageError
    ]);
}

$notifyEmail = trim((string) ($config["CONTACT_NOTIFY_EMAIL"] ?? ""));
$mailSent = false;

if ($notifyEmail !== "") {
    $mailSent = api_try_send_contact_mail($notifyEmail, $record);
}

api_json_response(200, [
    "status" => "ok",
    "id" => $record["id"],
    "stored" => true,
    "notified" => $mailSent
]);
