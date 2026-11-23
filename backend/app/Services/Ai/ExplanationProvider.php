<?php

namespace App\Services\Ai;

/** Any AI service that can write an explanation (so the provider can be changed later). */
interface ExplanationProvider
{
    public function isConfigured(): bool;

    /**
     * @throws AiUnavailable
     */
    public function answer(string $system, string $prompt, int $maxTokens): AiAnswer;

    /** Price of one answer in millionths of a US dollar. */
    public function costMicroUsd(AiAnswer $answer): int;
}
