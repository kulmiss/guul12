<?php

namespace App\Content;

/**
 * Plain, already-validated data read from the content files.
 * Everything in these Def classes is safe to import: ContentValidator built it.
 */
final class CurriculumDef
{
    /**
     * @param  list<SubjectDef>  $subjects
     * @param  list<MockDef>  $mocks
     */
    public function __construct(
        public readonly int $grade,
        public readonly array $subjects,
        public readonly array $mocks = [],
    ) {}
}
