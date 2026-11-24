<?php

namespace App\Content;

final class FlashcardDef
{
    public function __construct(
        public readonly string $uid,
        public readonly string $term,
        public readonly string $definition,
    ) {}
}
