<?php

namespace App\Support;

use App\Services\Leaderboard;
use Illuminate\Support\Carbon;

/**
 * "Today" and "this week" for staff pages, in Addis Ababa time (UTC+3), returned in UTC for queries.
 */
final class LocalTime
{
    public const TIMEZONE = 'Africa/Addis_Ababa';

    public static function startOfToday(): Carbon
    {
        return Carbon::now()->setTimezone(self::TIMEZONE)->startOfDay()->utc();
    }

    /** Start of the day $days - 1 days ago: 1 = today, 7 = today and the 6 days before. */
    public static function daysBack(int $days): Carbon
    {
        return self::startOfToday()->subDays(max(1, $days) - 1);
    }

    /** Monday 00:00, the same week the leaderboard uses. */
    public static function startOfWeek(): Carbon
    {
        return Leaderboard::weekStart(Carbon::now());
    }
}
