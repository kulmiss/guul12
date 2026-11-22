<?php

namespace App\Content;

/**
 * One answer from the independent second solve.
 */
final class ReviewDef
{
    public function __construct(
        public readonly string $uid,
        public readonly string $reviewer,
        public readonly string $hash,
        public readonly mixed $response,
        public readonly ?string $note,
    ) {}
}
