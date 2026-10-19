<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../api/bootstrap.php';
echo api_rate_limit_allow('parallel-test', 5, 60) ? '1' : '0';
