<?php

namespace App\Content;

final class OptionDef
{
    public function __construct(
        public readonly string $key,
        public readonly ?string $side,
        public readonly string $body,
    ) {}
}
