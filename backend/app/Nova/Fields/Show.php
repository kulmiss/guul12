<?php

namespace App\Nova\Fields;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;

/**
 * Small display helpers shared by all Nova resources, so dates, money and percentages look the same
 * everywhere. Times show in Addis Ababa time.
 */
final class Show
{
    public const TIMEZONE = 'Africa/Addis_Ababa';

    /**
     * A sortable time: "2 days ago" in lists, "23 Nov 2026, 13:00 (2 days ago)" on the detail page.
     *
     * @return list<Field>
     */
    public static function when(string $label, string $attribute): array
    {
        return [
            DateTime::make($label, $attribute)->sortable()->onlyOnIndex()
                ->displayUsing(fn (mixed $value): string => self::ago($value)),
            DateTime::make($label, $attribute)->onlyOnDetail()
                ->displayUsing(fn (mixed $value): string => self::full($value)),
        ];
    }

    public static function ago(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? Carbon::instance($value)->diffForHumans() : '—';
    }

    public static function full(mixed $value): string
    {
        if (! $value instanceof DateTimeInterface) {
            return '—';
        }
        $time = Carbon::instance($value);

        return $time->copy()->setTimezone(self::TIMEZONE)->format('j M Y, H:i').' ('.$time->diffForHumans().')';
    }

    public static function birr(?int $amount): string
    {
        return $amount === null ? '—' : number_format($amount).' Br';
    }

    public static function percent(int $part, int $whole): string
    {
        return $whole > 0 ? round(100 * $part / $whole).'%' : '—';
    }
}
