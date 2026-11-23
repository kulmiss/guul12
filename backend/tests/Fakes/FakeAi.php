<?php

namespace Tests\Fakes;

use App\Services\Ai\AiAnswer;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\ExplanationProvider;

/** Answers without calling any AI service; remembers the prompts. */
class FakeAi implements ExplanationProvider
{
    /** @var list<string> */
    public array $prompts = [];

    public bool $configured = true;

    public bool $fail = false;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function answer(string $system, string $prompt, int $maxTokens): AiAnswer
    {
        if ($this->fail) {
            throw new AiUnavailable('down');
        }
        $this->prompts[] = $prompt;

        return new AiAnswer('Step 1: add $2 + 2$. Answer #'.count($this->prompts).'.', 800, 400, 'claude-haiku-5-5');
    }

    public function costMicroUsd(AiAnswer $answer): int
    {
        return 280;
    }
}
