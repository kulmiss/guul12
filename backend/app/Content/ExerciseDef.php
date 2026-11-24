<?php

namespace App\Content;

final class ExerciseDef
{
    /**
     * @param  list<string>  $steps
     */
    public function __construct(
        public readonly string $uid,
        public readonly string $problem,
        public readonly array $steps,
        public readonly string $answer,
    ) {}
}
