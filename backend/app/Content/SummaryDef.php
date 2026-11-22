<?php

namespace App\Content;

final class SummaryDef
{
    /**
     * @param  list<string>  $keyPoints
     * @param  list<array{label: string, latex: string}>  $formulas
     */
    public function __construct(
        public readonly array $keyPoints,
        public readonly array $formulas,
        public readonly ?string $body,
    ) {}
}
