<?php

namespace App\Enums;

enum PracticeMode: string
{
    case Onboarding = 'onboarding';
    case Quick10 = 'quick10';
    case Chapter = 'chapter';
    case Unit = 'unit';
    case Mixed = 'mixed';
    case Timed = 'timed';
    case WrongOnly = 'wrong_only';
    case QuickRevise = 'quick_revise';
    case ChapterQuiz = 'chapter_quiz';
    // Spaced repetition: questions due again after 1, 3 and 7 days.
    case Review = 'review';
    // Adaptive practice: weak chapters first, then new questions.
    case Smart = 'smart';
    // The same 10 questions for every student of a grade and stream, once a day.
    case DailyChallenge = 'daily_challenge';
    // Two students, the same questions, each when they like.
    case Battle = 'battle';

    // Exams (no feedback until the end).
    case ExamFixed = 'exam_fixed';
    case ExamRandom = 'exam_random';
    case ExamWeak = 'exam_weak';
    case ExamMistakes = 'exam_mistakes';

    public function isExam(): bool
    {
        return in_array($this, [self::ExamFixed, self::ExamRandom, self::ExamWeak, self::ExamMistakes], true);
    }
}
