<?php

namespace App\Content;

use Database\Seeders\CurriculumSeeder;

/**
 * Opens the content folder with the subjects this app knows.
 */
final class ContentFolder
{
    public static function path(?string $override = null): string
    {
        $path = $override ?? config('content.path');

        return is_string($path) ? rtrim($path, '/') : '';
    }

    public static function validator(): ContentValidator
    {
        return new ContentValidator(array_keys(CurriculumSeeder::SUBJECTS));
    }
}
