<?php

namespace App\Content;

final class PassageDef
{
    public function __construct(
        public readonly string $uid,
        public readonly string $title,
        public readonly string $body,
    ) {}
}
