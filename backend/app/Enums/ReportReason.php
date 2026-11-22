<?php

namespace App\Enums;

/**
 * Why a student reports a question.
 */
enum ReportReason: string
{
    case WrongAnswer = 'wrong_answer';
    case Unclear = 'unclear';
    case Typo = 'typo';
    case Other = 'other';
}
