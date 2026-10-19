<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../api/bootstrap.php';
require __DIR__ . '/../api/assistant-v2/memory-store.php';
require __DIR__ . '/../api/assistant-v2/readiness.php';
require __DIR__ . '/../api/assistant-v2/close-manager.php';
require __DIR__ . '/../api/assistant-v2/policy-guard.php';
require __DIR__ . '/../api/assistant-v2/llm-client.php';
putenv('APP_LOAD_ENV=0');
putenv('API_CA_FILE=');

function check(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.15';
putenv('TRUSTED_PROXIES=');
check(!str_contains(api_safe_mail_subject("Solicitud\r\nBcc: qa@example.com"), "\n"), 'No permitir nuevas cabeceras en el asunto.');
check(api_try_send_plain_mail('', 'Prueba', 'Sin destinatario') === false, 'No invocar correo con destinatario vacio.');
check(api_client_ip() === '127.0.0.1', 'No confiar en cabeceras de proxy por defecto.');
putenv('TRUSTED_PROXIES=127.0.0.1');
check(api_client_ip() === '192.0.2.15', 'Proxy de confianza explicito.');
$_SERVER['HTTP_X_FORWARDED_FOR'] = 'invalido';
check(api_client_ip() === '127.0.0.1', 'Rechazar una IP reenviada invalida.');
putenv('TRUSTED_PROXIES=');

$base = PHP_OS_FAMILY === 'Windows' ? getenv('LOCALAPPDATA') . '/Temp/opencode' : sys_get_temp_dir();
$temporary = $base . '/portfolio-test-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
putenv('RATE_LIMIT_DIR=' . $temporary . '/rates');
try {
    for ($i = 0; $i < 3; $i++) check(api_rate_limit_allow('unit', 3, 60), 'Cuota inicial.');
    check(!api_rate_limit_allow('unit', 3, 60), 'Rechazar exceso de cuota.');
    $config = ['CHAT_V2_MEMORY_PATH' => $temporary . '/memory', 'CHAT_SESSION_TTL_SECONDS' => 300];
    $session = str_repeat('a', 64);
    $memory = agent_v2_default_memory($session);
    $memory['lead']['contact'] = ['name' => 'QA Demo', 'email' => 'qa@example.com', 'phone' => '', 'status' => 'provided'];
    $error = null;
    check(agent_v2_save_memory($memory, $config, $error), 'Escritura atomica.');
    check(agent_v2_load_memory($session, $config)['lead']['contact']['name'] === 'QA Demo', 'Lectura coherente.');
    $file = agent_v2_memory_file_path($session, $config);
    touch($file, time() - 3600);
    clearstatcache(true, $file);
    check(agent_v2_load_memory($session, $config)['lead']['contact']['name'] === '', 'No reanudar memoria caducada.');
    check(is_file($file), 'No borrar historiales existentes automaticamente.');
    unlink($file);
    rmdir($temporary . '/memory');

    $lead = agent_v2_default_memory($session)['lead'];
    $lead['objective'] = 'Captar consultas';
    $lead['timeline'] = 'Un mes';
    check(!agent_v2_can_send_quote($lead, 80, []), 'No cerrar sin contacto.');
    $lead['contact'] = ['name' => 'QA Demo', 'email' => 'qa@example.com'];
    check(agent_v2_can_send_quote($lead, 80, []), 'Briefing y contacto validos.');
    check(!agent_v2_is_confirmation_intent('no quiero enviar la propuesta'), 'Respetar rechazo.');
    check(agent_v2_is_confirmation_intent('confirmo enviar cotizacion'), 'Confirmacion explicita.');
    $result = agent_v2_next_action(['quoteSent' => true, 'confirmIntent' => true, 'canSendQuote' => true]);
    check($result['closeAttempt'] === 'none', 'No reenviar una cotizacion ya procesada.');
    $guard = agent_v2_apply_policy_guard([
        'config' => ['CHAT_KNOWLEDGE_PATH' => './knowledge/motor.json'],
        'reply' => 'Garantizamos ventas. Cuesta 200 euros. Que necesitas? Cual es tu plazo?',
        'nextAction' => 'explore', 'suggestions' => [],
    ]);
    check(substr_count($guard['reply'], '?') <= 1, 'Una pregunta por turno.');
    check(!str_contains($guard['reply'], '200 euros'), 'Evitar precios cerrados.');
    $reply = agent_v2_request_model_reply(['DEEPSEEK_API_KEY' => 'test_placeholder'], [], 0.3, 900,
        function ($url, $payload, $headers, $timeout) {
            check($payload['max_tokens'] <= 1200, 'Acotar salida.');
            return ['status' => 200, 'body' => '{"choices":[{"message":{"content":"Respuesta simulada"}}]}', 'error' => null];
        });
    check($reply === 'Respuesta simulada', 'Proveedor simulado.');
    if (extension_loaded('curl')) {
        $tls = api_tls_options();
        check($tls[CURLOPT_SSL_VERIFYPEER] === true && $tls[CURLOPT_SSL_VERIFYHOST] === 2, 'No desactivar TLS.');
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) check($tls[CURLOPT_SSL_OPTIONS] === CURLSSLOPT_NATIVE_CA, 'Confianza nativa.');
    }
    echo "OK: IP, cuotas, memoria, caducidad, cierre, policy guard y proveedor simulado.\n";
} finally {
    foreach (glob($temporary . '/rates/*.json') ?: [] as $file) unlink($file);
    if (is_dir($temporary . '/rates')) rmdir($temporary . '/rates');
    if (is_dir($temporary)) rmdir($temporary);
}
