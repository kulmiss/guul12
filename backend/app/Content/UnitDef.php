<?php

namespace App\Content;

final class UnitDef
{
    /**
     * @param  list<ChapterDef>  $chapters
     */
    public function __construct(
        public readonly int $number,
        public readonly string $title,
        public readonly ?string $stream,
        public readonly array $chapters,
    ) {}
}
