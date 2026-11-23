<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns an activity-log entry's data into "old → new" for people: rows for a table, a short line
 * for the list and escaped HTML for the detail page. A change entry (AuditLog::change) has
 * "old" and "new" lists of fields; every other key is an extra detail (for example the reason).
 */
final class AuditLogDiff
{
    private const SUMMARY_CHANGES = 2;

    private const SUMMARY_LENGTH = 120;

    /**
     * @param  array<string, mixed>|null  $data
     * @return list<array{field: string, old: string, new: string}>
     */
    public static function rows(?array $data): array
    {
        if (! self::isChange($data)) {
            return [];
        }
        /** @var array<string, mixed> $old */
        $old = $data['old'];
        /** @var array<string, mixed> $new */
        $new = $data['new'];
        $rows = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $rows[] = ['field' => self::label((string) $key), 'old' => self::value($old[$key] ?? null), 'new' => self::value($new[$key] ?? null)];
        }

        return $rows;
    }

    /**
     * Everything that is not the old/new pair.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, string>
     */
    public static function details(?array $data): array
    {
        $details = [];
        foreach ($data ?? [] as $key => $value) {
            if (self::isChange($data) && ($key === 'old' || $key === 'new')) {
                continue;
            }
            $details[self::label((string) $key)] = self::value($value);
        }

        return $details;
    }

    /** @param  array<string, mixed>|null  $data */
    public static function summary(?array $data): string
    {
        $rows = self::rows($data);
        if ($rows !== []) {
            $parts = array_map(fn (array $row): string => "{$row['field']}: {$row['old']} → {$row['new']}", array_slice($rows, 0, self::SUMMARY_CHANGES));
            $line = implode('; ', $parts).(count($rows) > self::SUMMARY_CHANGES ? ' (+'.(count($rows) - self::SUMMARY_CHANGES).' more)' : '');
        } else {
            $details = self::details($data);
            if ($details === []) {
                return '—';
            }
            $line = implode('; ', array_map(fn (string $field, string $value): string => "{$field}: {$value}", array_keys($details), $details));
        }

        return Str::limit($line, self::SUMMARY_LENGTH - 1, '…');
    }

    /** @param  array<string, mixed>|null  $data */
    public static function html(?array $data): string
    {
        $rows = self::rows($data);
        $details = self::details($data);
        if ($rows === [] && $details === []) {
            return '<p class="guul-diff">No details.</p>';
        }
        $e = fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<div class="guul-diff">';
        if ($rows !== []) {
            $html .= '<table class="gd-table"><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr><td>'.$e($row['field']).'</td><td><del class="gd-old">'.$e($row['old']).'</del></td><td><ins class="gd-new">'.$e($row['new']).'</ins></td></tr>';
            }
            $html .= '</tbody></table>';
        }
        if ($details !== []) {
            $html .= '<dl class="gd-details">';
            foreach ($details as $field => $value) {
                $html .= '<dt>'.$e($field).'</dt><dd>'.$e($value).'</dd>';
            }
            $html .= '</dl>';
        }

        return $html.'</div>';
    }

    /**
     * @param  array<string, mixed>|null  $data
     *
     * @phpstan-assert-if-true array{old: array<string, mixed>, new: array<string, mixed>} $data
     */
    private static function isChange(?array $data): bool
    {
        return is_array($data['old'] ?? null) && is_array($data['new'] ?? null);
    }

    private static function label(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    private static function value(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            is_scalar($value) => (string) $value,
            default => '—',
        };
    }
}
