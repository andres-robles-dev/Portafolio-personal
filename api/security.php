<?php

function api_is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

function api_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        api_error_response(405, 'METHOD_NOT_ALLOWED', 'Solo se permite POST para este endpoint.');
    }
    if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && !in_array($_SERVER['HTTP_SEC_FETCH_SITE'], ['same-origin', 'none'], true)) {
        api_error_response(403, 'ORIGIN_NOT_ALLOWED', 'Origen no permitido.');
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $expected = (api_is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
    if ($origin !== '' && strtolower($origin) !== strtolower($expected)) {
        api_error_response(403, 'ORIGIN_NOT_ALLOWED', 'Origen no permitido.');
    }
}

function api_validate_text_fields(array $body, array $limits): void
{
    foreach ($limits as $field => $limit) {
        if (!array_key_exists($field, $body)) continue;
        if (!is_string($body[$field]) || strlen($body[$field]) > $limit) {
            api_error_response(422, 'VALIDATION_ERROR', 'Uno de los campos tiene un tipo o longitud no permitido.');
        }
    }
}

function api_chat_session_id(): string
{
    $token = $_COOKIE['portfolio_chat_owner_v2'] ?? '';
    if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        setcookie('portfolio_chat_owner_v2', $token, [
            'expires' => time() + 604800,
            'path' => '/api',
            'secure' => api_is_https(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
    return hash('sha256', 'portfolio-chat-v2:' . $token);
}

function api_private_directory(string $directory): string
{
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Private directory unavailable');
    }
    return (string) realpath($directory);
}

function api_tls_options(): array
{
    $options = [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
    $caFile = trim(api_env_value('API_CA_FILE'));
    if ($caFile !== '') {
        if (!is_file($caFile) || !is_readable($caFile)) throw new RuntimeException('CA unavailable');
        $options[CURLOPT_CAINFO] = $caFile;
    } elseif (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
        $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
    }
    return $options;
}
