<?php

namespace App\Enums;

/**
 * The eight question types the app supports.
 *
 * Answer key shape (questions.answer_key JSON) per type:
 * - single_choice / true_false / image_choice / passage_choice: {"correct": "B"}
 * - multiple_answers: {"correct": ["A", "C"]}
 * - numeric: {"value": 2.5, "tolerance": 0.01, "unit": "s"}
 * - fill_in: {"accepted": ["photosynthesis", "photo-synthesis"]}
 * - matching: {"pairs": {"L1": "R3", "L2": "R1"}}
 * - ordering: {"order": ["C", "A", "B"]}
 */
enum QuestionType: string
{
    case SingleChoice = 'single_choice';
    case TrueFalse = 'true_false';
    case FillIn = 'fill_in';
    case Numeric = 'numeric';
    case Matching = 'matching';
    case Ordering = 'ordering';
    case MultipleAnswers = 'multiple_answers';
    case ImageChoice = 'image_choice';
    case PassageChoice = 'passage_choice';

    /**
     * Types whose answer is one option key.
     */
    public function isSingleKey(): bool
    {
        return in_array($this, [self::SingleChoice, self::TrueFalse, self::ImageChoice, self::PassageChoice], true);
    }

    /**
     * Types that use rows in question_options.
     */
    public function usesOptions(): bool
    {
        return ! in_array($this, [self::FillIn, self::Numeric], true);
    }
}
