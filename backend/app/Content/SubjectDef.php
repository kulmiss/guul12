<?php

namespace App\Content;

final class SubjectDef
{
    /**
     * @param  list<UnitDef>  $units
     */
    public function __construct(
        public readonly string $code,
        public readonly ?string $textbookUrl,
        public readonly array $units,
    ) {}
}
