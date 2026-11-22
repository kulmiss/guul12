<?php

namespace App\Services;

/**
 * Blocks rude nicknames. The word list is a first line of defence; reports in Nova catch the rest.
 * Matching ignores case, spaces, punctuation and common letter swaps (0→o, 1→i, 3→e, 4→a, 5→s, 7→t, @→a, $→s).
 */
class NicknameFilter
{
    /**
     * English base list. Somali and Amharic words are added through config/nickname_blocklist.php
     * after native review.
     *
     * @var list<string>
     */
    private const BASE = [
        'fuck', 'shit', 'bitch', 'bastard', 'dick', 'pussy', 'cunt', 'whore', 'slut', 'nigger', 'nigga',
        'fag', 'rape', 'porn', 'sex', 'nazi', 'hitler', 'asshole', 'penis', 'vagina', 'admin', 'moderator',
        'kulmiss', 'guul12',
    ];

    public function isAllowed(string $nickname): bool
    {
        $squashed = self::squash($nickname);

        foreach ($this->words() as $word) {
            $needle = self::squash($word);

            if ($needle !== '' && str_contains($squashed, $needle)) {
                return false;
            }
        }

        return true;
    }

    public static function squash(string $text): string
    {
        $text = mb_strtolower($text);
        $text = strtr($text, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']);

        return (string) preg_replace('/[^\p{L}]/u', '', $text);
    }

    /**
     * @return list<string>
     */
    private function words(): array
    {
        /** @var list<string> $extra */
        $extra = config('nickname_blocklist.words', []);

        return array_values(array_unique([...self::BASE, ...$extra]));
    }
}
