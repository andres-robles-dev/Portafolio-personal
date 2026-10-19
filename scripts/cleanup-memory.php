<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../api/bootstrap.php';
$config = api_load_config();
$directory = api_resolve_storage_path($config['CHAT_V2_MEMORY_PATH']);
$ttl = max(300, min(2592000, (int) $config['CHAT_SESSION_TTL_SECONDS']));
$apply = in_array('--apply', $argv, true);
$expired = 0;
$deleted = 0;
foreach (glob($directory . '/*.json') ?: [] as $file) {
    if (!is_file($file) || is_link($file) || filemtime($file) >= time() - $ttl) continue;
    $expired++;
    if ($apply && unlink($file)) $deleted++;
}
echo json_encode(['modo' => $apply ? 'aplicar' : 'solo_revision', 'memoriasCaducadas' => $expired, 'eliminadas' => $deleted], JSON_PRETTY_PRINT) . PHP_EOL;
