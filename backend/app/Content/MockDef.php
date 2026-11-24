<?php

namespace App\Content;

use App\Enums\Access;

final class MockDef
{
    /**
     * @param  list<string>  $questionUids  in exam order
     */
    public function __construct(
        public readonly string $uid,
        public readonly string $subjectCode,
        public readonly int $number,
        public readonly string $title,
        public readonly int $minutes,
        public readonly Access $access,
        public readonly array $questionUids,
    ) {}
}
