<?php

namespace App\Enums;

/**
 * Content workflow: draft → checked → beta → published → reported → fixed.
 */
enum QuestionStatus: string
{
    case Draft = 'draft';
    case Checked = 'checked';
    case Beta = 'beta';
    case Published = 'published';
    case Reported = 'reported';
    case Fixed = 'fixed';
    // Removed from the content files; kept because old attempts point to it. Never shown.
    case Retired = 'retired';

    /**
     * Statuses students can see in the app.
     *
     * @return list<self>
     */
    public static function visibleToStudents(): array
    {
        return [self::Beta, self::Published, self::Reported, self::Fixed];
    }

    public function isVisibleToStudents(): bool
    {
        return in_array($this, self::visibleToStudents(), true);
    }
}
