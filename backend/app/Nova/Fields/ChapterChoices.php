<?php

namespace App\Nova\Fields;

use App\Models\Chapter;

/**
 * Chapters for a drop-down, in book order: "Biology · Grade 12 · 1.2 Cell parts".
 */
final class ChapterChoices
{
    /** @return array<int, string> */
    public static function all(): array
    {
        $out = [];
        foreach (Chapter::query()->with('unit.subject')->orderBy('unit_id')->orderBy('number')->get() as $c) {
            $out[$c->id] = $c->unit->subject->name.' · Grade '.$c->unit->grade.' · '.$c->unit->number.'.'.$c->number.' '.$c->title;
        }

        return $out;
    }
}
