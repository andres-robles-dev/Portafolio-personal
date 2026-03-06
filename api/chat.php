<?php

require __DIR__ . DIRECTORY_SEPARATOR . "bootstrap.php";
require __DIR__ . DIRECTORY_SEPARATOR . "assistant-v2" . DIRECTORY_SEPARATOR . "orchestrator.php";

$config = api_load_config();
agent_v2_handle_chat($config);
