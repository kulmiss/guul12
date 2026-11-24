<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Claude through the Messages API. */
class AnthropicProvider implements ExplanationProvider
{
    public const URL = 'https://api.anthropic.com/v1/messages';

    public const VERSION = '2023-06-01';

    public function __construct(
        private readonly ?string $key,
        private readonly string $model,
        private readonly float $priceInPerMTok,
        private readonly float $priceOutPerMTok,
    ) {}

    public function isConfigured(): bool
    {
        return $this->key !== null && trim($this->key) !== '';
    }

    public function answer(string $system, string $prompt, int $maxTokens): AiAnswer
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailable('No API key.');
        }
        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) $this->key,
                'anthropic-version' => self::VERSION,
            ])->acceptJson()->timeout(30)->post(self::URL, [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
        } catch (ConnectionException $e) {
            throw new AiUnavailable('No connection to the AI service.', 0, $e);
        }
        if (! $response->successful()) {
            // Never log or return the key; the status is enough.
            throw new AiUnavailable('AI service answered '.$response->status().'.');
        }

        $text = '';
        $content = $response->json('content');
        foreach (is_array($content) ? $content : [] as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $text .= $block['text'];
            }
        }
        $text = trim($text);
        if ($text === '') {
            throw new AiUnavailable('Empty answer.');
        }

        return new AiAnswer(
            $text,
            (int) $response->json('usage.input_tokens', 0),
            (int) $response->json('usage.output_tokens', 0),
            is_string($response->json('model')) ? (string) $response->json('model') : $this->model,
        );
    }

    public function costMicroUsd(AiAnswer $answer): int
    {
        // price per million tokens × tokens = micro-dollars.
        return (int) ceil($answer->inputTokens * $this->priceInPerMTok + $answer->outputTokens * $this->priceOutPerMTok);
    }
}
