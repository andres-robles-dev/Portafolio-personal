<?php

$root = realpath(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (str_contains($path, "\0") || str_contains($path, '\\') || preg_match('~(^|/)[.]~', $path)) {
    http_response_code(404);
    echo 'No encontrado';
    return true;
}
$aliases = ['/api/chat' => '/api/chat.php', '/api/contact' => '/api/contact.php', '/api/health' => '/api/health.php'];
$path = $aliases[$path] ?? $path;
$endpoints = ['/api/chat.php', '/api/contact.php', '/api/health.php'];
$public = in_array($path, ['/', '/index.html', '/asistente.html', '/release.json'], true)
    || (preg_match('~^/(assets|img)/~', $path) && preg_match('~\.(css|js|svg|png|jpe?g|gif|webp|ico|woff2?|ttf|otf|avif|mp4)$~i', $path));
$file = realpath($root . ($path === '/' ? '/index.html' : $path));
$prefix = str_replace('\\', '/', $root) . '/';
$canonical = str_replace('\\', '/', $file ?: '');
$relative = substr($canonical, strlen($prefix));
$canonicalPublic = in_array($relative, ['index.html', 'asistente.html', 'release.json'], true)
    || (preg_match('~^(assets|img)/~', $relative) && preg_match('~\.(css|js|svg|png|jpe?g|gif|webp|ico|woff2?|ttf|otf|avif|mp4)$~i', $relative));
if (PHP_OS_FAMILY === 'Windows') { $prefix = strtolower($prefix); $canonical = strtolower($canonical); }
if (!$file || !is_file($file) || !str_starts_with($canonical, $prefix)
    || ($public && !$canonicalPublic)
    || preg_match('~(^|/)[.]|\\\\~', $path) || (!$public && !in_array($path, $endpoints, true))) {
    http_response_code(404);
    echo 'No encontrado';
    return true;
}
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
if (in_array($path, $endpoints, true)) {
    require $file;
    return true;
}
if (in_array($path, ['/', '/index.html', '/asistente.html', '/release.json'], true)) {
    header('Content-Type: ' . (str_ends_with($file, '.json') ? 'application/json' : 'text/html') . '; charset=utf-8');
    header('Cache-Control: no-store');
    readfile($file);
    return true;
}
$mime = [
    'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
    'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon', 'avif' => 'image/avif',
    'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'mp4' => 'video/mp4',
];
header('Content-Type: ' . ($mime[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
header('Cache-Control: no-cache');
readfile($file);
return true;
