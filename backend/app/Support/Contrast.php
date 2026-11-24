<?php

namespace App\Support;

/**
 * WCAG 2.1 colour contrast. Colours are "#RRGGBB".
 */
final class Contrast
{
    public const AA_TEXT = 4.5;

    public static function ratio(string $a, string $b): float
    {
        [$high, $low] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($high + 0.05) / ($low + 0.05);
    }

    /** White or the given dark text colour, whichever reads better on the background. */
    public static function bestOn(string $background, string $darkText): string
    {
        return self::ratio('#FFFFFF', $background) >= self::ratio($darkText, $background) ? '#FFFFFF' : $darkText;
    }

    /**
     * Mixes $color towards $towards (0 = $color, 1 = $towards).
     */
    public static function mix(string $color, string $towards, float $amount): string
    {
        $a = self::rgb($color);
        $b = self::rgb($towards);
        $out = '#';
        foreach ([0, 1, 2] as $i) {
            $out .= sprintf('%02X', (int) round($a[$i] + ($b[$i] - $a[$i]) * $amount));
        }

        return $out;
    }

    /** The colour made darker, step by step, until it reaches $needed contrast on the background. */
    public static function readableOn(string $color, string $background, float $needed = self::AA_TEXT): string
    {
        for ($step = 0; $step <= 20; $step++) {
            $candidate = self::mix($color, '#000000', $step / 20);
            if (self::ratio($candidate, $background) >= $needed) {
                return $candidate;
            }
        }

        return '#000000';
    }

    /** @return array{0: int, 1: int, 2: int} */
    public static function rgb(string $color): array
    {
        $hex = ltrim($color, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    private static function luminance(string $color): float
    {
        $channels = array_map(function (int $c): float {
            $s = $c / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }, self::rgb($color));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
