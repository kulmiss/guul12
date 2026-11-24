<?php

namespace App\Content;

final class ChapterDef
{
    /**
     * @param  list<ExerciseDef>  $exercises
     * @param  list<FlashcardDef>  $flashcards
     * @param  list<PassageDef>  $passages
     * @param  list<QuestionDef>  $questions
     */
    public function __construct(
        public readonly string $code,
        public readonly int $number,
        public readonly string $title,
        public readonly ?string $file,
        public readonly ?SummaryDef $summary,
        public readonly array $exercises,
        public readonly array $flashcards,
        public readonly array $passages,
        public readonly array $questions,
        /** Absolute path of the built summary PDF (content/pdf/…), or null when there is none. */
        public readonly ?string $summaryPdf = null,
    ) {}

    public function passage(?string $uid): ?PassageDef
    {
        foreach ($this->passages as $p) {
            if ($p->uid === $uid) {
                return $p;
            }
        }

        return null;
    }
}
