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
}
