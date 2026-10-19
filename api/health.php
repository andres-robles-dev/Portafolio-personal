<?php

require __DIR__ . '/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') api_error_response(405, 'METHOD_NOT_ALLOWED', 'Solo se permite GET.');
$config = api_load_config();
$expected = trim((string) $config['HEALTHCHECK_TOKEN']);
$incoming = trim((string) ($_SERVER['HTTP_X_HEALTH_TOKEN'] ?? ''));
if ($expected === '' || $incoming === '' || !hash_equals($expected, $incoming)) {
    api_error_response(403, 'FORBIDDEN', 'Healthcheck no autorizado.');
}
$demo = $config['APP_MODE'] === 'demo';
$checks = [
    'https' => api_is_https(),
    'displayErrorsOff' => in_array(strtolower((string) ini_get('display_errors')), ['0', 'off', ''], true),
    'storageWritable' => $demo || is_writable(dirname(api_resolve_storage_path($config['CONTACT_STORAGE_PATH']))),
    'memoryWritable' => $demo || is_writable(api_resolve_storage_path($config['CHAT_V2_MEMORY_PATH'])),
    'providerConfigured' => $demo || ($config['DEEPSEEK_API_KEY'] !== '' && extension_loaded('curl')),
];
$ok = !in_array(false, $checks, true);
api_json_response($ok ? 200 : 503, ['ok' => $ok, 'checks' => $checks, 'mode' => $config['APP_MODE']]);
