<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "knowledge.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "retrieval-core.php";

function agent_v2_retrieve_context_text(array $config, string $message): string
{
    $documents = agent_v2_load_knowledge_catalog($config);
    $blocks = agent_v2_flatten_knowledge_blocks($documents);

    if (empty($blocks)) {
        return "Sin bloques de contexto disponibles.";
    }

    $limit = max(1, (int) ($config["CHAT_RETRIEVAL_BLOCKS"] ?? 4));
    $selected = agent_v2_retrieve_context_blocks($message, $blocks, $limit);
    return agent_v2_context_to_text($selected);
}
