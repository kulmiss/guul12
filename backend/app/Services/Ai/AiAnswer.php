<?php

namespace App\Services\Ai;

final readonly class AiAnswer
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
    ) {}
}
