<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "bootstrap.php";

function agent_v2_knowledge_dir(array $config): string
{
    $relativePath = trim((string) ($config["CHAT_KNOWLEDGE_PATH"] ?? "./knowledge"));

    if ($relativePath === "") {
        $relativePath = "./knowledge";
    }

    return api_resolve_storage_path($relativePath);
}

function agent_v2_load_knowledge_catalog(array $config): array
{
    $directory = agent_v2_knowledge_dir($config);

    if (!is_dir($directory)) {
        return [];
    }

    $pattern = $directory . DIRECTORY_SEPARATOR . "*.json";
    $files = glob($pattern);

    if (!is_array($files)) {
        return [];
    }

    $documents = [];

    foreach ($files as $filePath) {
        $raw = @file_get_contents($filePath);

        if (!is_string($raw) || $raw === "") {
            continue;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !isset($decoded["blocks"]) || !is_array($decoded["blocks"])) {
            continue;
        }

        $decoded["document"] = (string) ($decoded["document"] ?? pathinfo($filePath, PATHINFO_FILENAME));
        $decoded["priority"] = (string) ($decoded["priority"] ?? "medium");
        $documents[] = $decoded;
    }

    return $documents;
}

function agent_v2_flatten_knowledge_blocks(array $documents): array
{
    $blocks = [];

    foreach ($documents as $document) {
        $documentName = (string) ($document["document"] ?? "document");
        $documentPriority = (string) ($document["priority"] ?? "medium");

        foreach ($document["blocks"] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $content = trim((string) ($block["content"] ?? ""));

            if ($content === "") {
                continue;
            }

            $blocks[] = [
                "id" => (string) ($block["id"] ?? ($documentName . "-" . count($blocks))),
                "title" => trim((string) ($block["title"] ?? "Bloque de contexto")),
                "content" => $content,
                "keywords" => is_array($block["keywords"] ?? null) ? $block["keywords"] : [],
                "document" => $documentName,
                "priority" => $documentPriority
            ];
        }
    }

    return $blocks;
}
