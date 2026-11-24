<?php

namespace App\Content;

use App\Enums\QuestionType;

final class QuestionDef
{
    /**
     * @param  list<OptionDef>  $options
     * @param  array<string, mixed>  $answerKey  in the shape AnswerScorer reads
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $uid,
        public readonly QuestionType $type,
        public readonly int $difficulty,
        public readonly string $stem,
        public readonly ?string $media,
        public readonly ?string $passageUid,
        public readonly array $options,
        public readonly array $answerKey,
        public readonly string $explanation,
        public readonly string $source,
        public readonly array $tags,
    ) {}

    /**
     * Hash of what a student must read to answer (not the key or explanation).
     * A second solve counts only for this exact hash.
     */
    public function reviewHash(?PassageDef $passage): string
    {
        return hash('sha256', (string) json_encode([
            $this->type->value,
            $this->stem,
            $this->media,
            $passage?->body,
            array_map(fn (OptionDef $o) => [$o->key, $o->side, $o->body], $this->options),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Hash of everything the app shows. Unchanged hash = nothing to import.
     */
    public function contentHash(?PassageDef $passage): string
    {
        return hash('sha256', (string) json_encode([
            $this->reviewHash($passage),
            $this->answerKey,
            $this->explanation,
            $this->difficulty,
            $this->source,
            $this->tags,
        ], JSON_UNESCAPED_UNICODE));
    }
}
