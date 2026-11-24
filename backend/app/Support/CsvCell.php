<?php

namespace App\Support;

/**
 * Makes a value safe for a CSV that staff open in Excel or Google Sheets: text that starts like a
 * formula (=, +, -, @, tab, return) gets a leading apostrophe, so it is shown, never run.
 */
final class CsvCell
{
    public static function safe(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $text = is_scalar($value) ? (string) $value : '';

        return $text !== '' && str_contains("=+-@\t\r", $text[0]) ? "'".$text : $text;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string|int|float>
     */
    public static function row(array $row): array
    {
        return array_map(self::safe(...), $row);
    }
}
