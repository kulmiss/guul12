<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

/**
 * Private student IDs like "ST-7K3Q9": easy to read aloud and type on WhatsApp.
 * The alphabet leaves out 0/O, 1/I/L so students cannot mix them up.
 */
class StudentIdGenerator
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const LENGTH = 5;

    public const PREFIX = 'ST-';

    public const PATTERN = '/^ST-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{5}$/';

    private const MAX_TRIES = 20;

    public function generate(): string
    {
        for ($try = 0; $try < self::MAX_TRIES; $try++) {
            $candidate = $this->candidate();

            if (! User::query()->where('student_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Could not generate a unique student ID.');
    }

    public function candidate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $id = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $id .= self::ALPHABET[random_int(0, $max)];
        }

        return self::PREFIX.$id;
    }

    /**
     * Normalises what a helper types in Nova search: "st 7k3q9" → "ST-7K3Q9".
     */
    public static function normalise(string $input): string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));

        if (str_starts_with($clean, 'ST')) {
            $clean = substr($clean, 2);
        }

        return self::PREFIX.$clean;
    }

    public static function isValid(string $studentId): bool
    {
        return preg_match(self::PATTERN, $studentId) === 1;
    }
}
