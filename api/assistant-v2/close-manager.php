<?php

function agent_v2_next_action(array $state): array
{
    $quoteSent = (bool) ($state["quoteSent"] ?? false);
    $readinessScore = (int) ($state["readinessScore"] ?? 0);
    $threshold = (int) ($state["threshold"] ?? 70);
    $contactComplete = (bool) ($state["contactComplete"] ?? false);
    $canSendQuote = (bool) ($state["canSendQuote"] ?? false);
    $priceIntent = (bool) ($state["priceIntent"] ?? false);
    $confirmIntent = (bool) ($state["confirmIntent"] ?? false);
    $needsReframe = (bool) ($state["needsReframe"] ?? false);
    $closeAttempts = max(0, (int) ($state["closeAttempts"] ?? 0));
    $maxSoftAttempts = max(1, (int) ($state["maxSoftAttempts"] ?? 2));
    $missingBriefFields = is_array($state["missingBriefFields"] ?? null) ? $state["missingBriefFields"] : [];

    if ($quoteSent) {
        return [
            "nextBestAction" => "handoff",
            "closeAttempt" => "none",
            "incrementCloseAttempt" => false
        ];
    }

    if ($confirmIntent && $canSendQuote) {
        return [
            "nextBestAction" => "handoff",
            "closeAttempt" => "final",
            "incrementCloseAttempt" => false
        ];
    }

    if ($needsReframe) {
        return [
            "nextBestAction" => "reframe",
            "closeAttempt" => "none",
            "incrementCloseAttempt" => false
        ];
    }

    if ($canSendQuote) {
        return [
            "nextBestAction" => "close_final",
            "closeAttempt" => "final",
            "incrementCloseAttempt" => false
        ];
    }

    $closeWindowThreshold = max(45, $threshold - 15);
    $closeWindowOpen = $readinessScore >= $threshold || ($priceIntent && $readinessScore >= $closeWindowThreshold);

    if ($closeWindowOpen && !$contactComplete) {
        if ($closeAttempts < $maxSoftAttempts) {
            return [
                "nextBestAction" => "close_soft",
                "closeAttempt" => $closeAttempts === 0 ? "soft1" : "soft2",
                "incrementCloseAttempt" => true
            ];
        }

        return [
            "nextBestAction" => "close_final",
            "closeAttempt" => "final",
            "incrementCloseAttempt" => false
        ];
    }

    if (!empty($missingBriefFields)) {
        return [
            "nextBestAction" => "clarify",
            "closeAttempt" => "none",
            "incrementCloseAttempt" => false
        ];
    }

    return [
        "nextBestAction" => "explore",
        "closeAttempt" => "none",
        "incrementCloseAttempt" => false
    ];
}
