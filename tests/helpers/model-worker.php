<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../api/bootstrap.php';
require __DIR__ . '/../../api/assistant-v2/llm-client.php';
$case = $argv[1] ?? 'valid';
$responses = [
    'tls' => ['status' => 0, 'body' => '', 'error' => 'TRANSPORT_ERROR', 'errno' => 60],
    'timeout' => ['status' => 0, 'body' => '', 'error' => 'TRANSPORT_ERROR', 'errno' => 28],
    'auth' => ['status' => 401, 'body' => 'detalle interno oculto', 'error' => null],
    'quota' => ['status' => 429, 'body' => 'detalle interno oculto', 'error' => null],
    'invalid' => ['status' => 200, 'body' => '{invalido', 'error' => null],
    'valid' => ['status' => 200, 'body' => '{"choices":[{"message":{"content":"Respuesta simulada"}}]}', 'error' => null],
];
$reply = agent_v2_request_model_reply(['DEEPSEEK_API_KEY' => 'test_placeholder'], [], 0.35, 900,
    static function () use ($responses, $case) { return $responses[$case]; });
echo json_encode(['reply' => $reply]);
