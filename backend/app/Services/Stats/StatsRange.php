<?php

namespace App\Services\Stats;

use App\Services\Settings;
use Illuminate\Support\Carbon;

/**
 * A dashboard range (7 / 30 / 90 days, this term, all time) as UTC times, plus the period
 * just before it for the ↑/↓ % comparison. Days follow Addis Ababa time.
 */
final class StatsRange
{
    public const TIMEZONE = 'Africa/Addis_Ababa';

    public const KEYS = ['1', '7', '30', '90', 'TERM', 'ALL'];

    private function __construct(
        public readonly string $key,
        public readonly ?Carbon $start,
        public readonly Carbon $end,
        public readonly ?Carbon $previousStart,
        public readonly ?Carbon $previousEnd,
    ) {}

    /** @return array<int|string, string> range key => label, as Nova cards show them (PHP keeps '7' as the number 7) */
    public static function options(): array
    {
        return ['1' => 'Today', '7' => '7 days', '30' => '30 days', '90' => '90 days', 'TERM' => 'This term', 'ALL' => 'All time'];
    }

    public static function make(string $key, ?Carbon $now = null): self
    {
        $now = ($now ?? Carbon::now())->copy()->utc();
        $key = in_array($key, self::KEYS, true) ? $key : '30';

        if ($key === 'ALL') {
            return new self($key, null, $now, null, null);
        }

        $todayStart = $now->copy()->setTimezone(self::TIMEZONE)->startOfDay();
        $start = $key === 'TERM'
            ? self::termStart($now)
            // "Today" = since local midnight; "7 days" = today and the 6 days before it.
            : $todayStart->copy()->subDays((int) $key - 1);
        $start = $start->utc();
        $length = (int) $start->diffInSeconds($now, true);

        return new self($key, $start, $now, $start->copy()->subSeconds($length), $start->copy());
    }

    /**
     * Start of the current term: the most recent 11 September or 1 February, or the date in the
     * settings when it is later than that (and not in the future). An old setting is ignored next term.
     */
    public static function termStart(Carbon $now): Carbon
    {
        $local = $now->copy()->setTimezone(self::TIMEZONE);
        $auto = self::automaticTermStart($local);
        $setting = app(Settings::class)->get('term_starts_on');
        if (is_string($setting) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $setting) === 1) {
            $date = Carbon::createFromFormat('Y-m-d', $setting, self::TIMEZONE)?->startOfDay();
            if ($date !== null && $date->lessThanOrEqualTo($local) && $date->greaterThan($auto)) {
                return $date;
            }
        }

        return $auto;
    }

    private static function automaticTermStart(Carbon $local): Carbon
    {
        $september = Carbon::create($local->year, 9, 11, 0, 0, 0, self::TIMEZONE);
        $february = Carbon::create($local->year, 2, 1, 0, 0, 0, self::TIMEZONE);
        if ($september !== null && $local->greaterThanOrEqualTo($september)) {
            return $september;
        }
        if ($february !== null && $local->greaterThanOrEqualTo($february)) {
            return $february;
        }

        return Carbon::create($local->year - 1, 9, 11, 0, 0, 0, self::TIMEZONE) ?? $local->copy()->startOfYear();
    }

    /** Number of whole local days the range covers (for per-day charts; all time is capped). */
    public function days(int $maxForAllTime = 365): int
    {
        if ($this->start === null) {
            return $maxForAllTime;
        }

        $first = $this->start->copy()->setTimezone(self::TIMEZONE)->startOfDay();
        $last = $this->end->copy()->setTimezone(self::TIMEZONE)->startOfDay();

        return (int) $first->diffInDays($last) + 1;
    }
}
