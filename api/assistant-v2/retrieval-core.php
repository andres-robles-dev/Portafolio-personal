<?php

function agent_v2_tokenize(string $text): array
{
    $clean = strtolower(trim($text));

    if ($clean === "") {
        return [];
    }

    $clean = preg_replace("/[^a-z0-9\s]+/i", " ", $clean);
    $tokens = preg_split("/\s+/", (string) $clean, -1, PREG_SPLIT_NO_EMPTY);

    if (!is_array($tokens)) {
        return [];
    }

    $filtered = [];

    foreach ($tokens as $token) {
        $normalized = trim((string) $token);

        if ($normalized === "" || strlen($normalized) < 2) {
            continue;
        }

        $filtered[] = $normalized;
    }

    return array_values(array_unique($filtered));
}

function agent_v2_priority_boost(string $priority): int
{
    if ($priority === "high") {
        return 3;
    }

    if ($priority === "low") {
        return 0;
    }

    return 1;
}

function agent_v2_score_block(array $queryTokens, array $block): int
{
    if (empty($queryTokens)) {
        return 0;
    }

    $score = agent_v2_priority_boost((string) ($block["priority"] ?? "medium"));
    $titleTokens = agent_v2_tokenize((string) ($block["title"] ?? ""));
    $contentTokens = agent_v2_tokenize((string) ($block["content"] ?? ""));
    $keywordTokens = [];

    foreach (($block["keywords"] ?? []) as $keyword) {
        foreach (agent_v2_tokenize((string) $keyword) as $token) {
            $keywordTokens[] = $token;
        }
    }

    $titleSet = array_fill_keys($titleTokens, true);
    $contentSet = array_fill_keys($contentTokens, true);
    $keywordSet = array_fill_keys($keywordTokens, true);

    foreach ($queryTokens as $token) {
        if (isset($keywordSet[$token])) {
            $score += 6;
        }

        if (isset($titleSet[$token])) {
            $score += 4;
        }

        if (isset($contentSet[$token])) {
            $score += 2;
        }
    }

    return $score;
}

function agent_v2_default_context_blocks(array $blocks, int $limit): array
{
    $preferredIds = [
        "value-proposition",
        "tone-rules",
        "scope-rule",
        "cap-stack-policy",
        "cap-mobile-limit",
        "qualification-questions",
        "pricing-answer-rule",
        "cta-contact"
    ];

    $selected = [];

    foreach ($preferredIds as $id) {
        foreach ($blocks as $block) {
            if (($block["id"] ?? "") === $id) {
                $selected[] = $block;
                break;
            }
        }
    }

    if (count($selected) < $limit) {
        foreach ($blocks as $block) {
            if (count($selected) >= $limit) {
                break;
            }

            $alreadySelected = false;

            foreach ($selected as $selectedBlock) {
                if (($selectedBlock["id"] ?? "") === ($block["id"] ?? "")) {
                    $alreadySelected = true;
                    break;
                }
            }

            if (!$alreadySelected) {
                $selected[] = $block;
            }
        }
    }

    return array_slice($selected, 0, $limit);
}

function agent_v2_retrieve_context_blocks(string $message, array $blocks, int $limit = 4): array
{
    $safeLimit = max(1, $limit);
    $queryTokens = agent_v2_tokenize($message);

    if (empty($blocks)) {
        return [];
    }

    $ranked = [];

    foreach ($blocks as $block) {
        $score = agent_v2_score_block($queryTokens, $block);

        if ($score <= 0) {
            continue;
        }

        $ranked[] = [
            "score" => $score,
            "block" => $block
        ];
    }

    if (empty($ranked)) {
        return agent_v2_default_context_blocks($blocks, $safeLimit);
    }

    usort($ranked, static function ($left, $right) {
        return ($right["score"] <=> $left["score"]);
    });

    $selected = [];

    foreach ($ranked as $entry) {
        if (count($selected) >= $safeLimit) {
            break;
        }

        $selected[] = $entry["block"];
    }

    return $selected;
}

function agent_v2_context_to_text(array $blocks): string
{
    if (empty($blocks)) {
        return "Sin bloques de contexto disponibles.";
    }

    $lines = [];

    foreach ($blocks as $block) {
        $title = trim((string) ($block["title"] ?? "Contexto"));
        $content = trim((string) ($block["content"] ?? ""));

        if ($content === "") {
            continue;
        }

        $lines[] = "- " . $title . ": " . $content;
    }

    if (empty($lines)) {
        return "Sin bloques de contexto disponibles.";
    }

    return implode("\n", $lines);
}
