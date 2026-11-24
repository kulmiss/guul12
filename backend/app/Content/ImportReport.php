<?php

namespace App\Content;

final class ImportReport
{
    /** @var array<string, int> */
    public array $counts = [];

    /** @var list<string> */
    public array $problems = [];

    /** @var list<int> */
    public array $toRescore = [];

    public int $answersRescored = 0;

    public function count(string $what): void
    {
        $this->counts[$what] = ($this->counts[$what] ?? 0) + 1;
    }

    public function problem(string $text): void
    {
        $this->problems[] = $text;
    }

    public function get(string $what): int
    {
        return $this->counts[$what] ?? 0;
    }
}
