<?php

namespace App\Nova\Resources;

use App\Enums\QuestionStatus;

final class StatusOptions
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $out = [];

        foreach (QuestionStatus::cases() as $status) {
            $out[$status->value] = ucfirst($status->value);
        }

        return $out;
    }
}
