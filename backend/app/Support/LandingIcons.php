<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Small line icons for the website, drawn inline (no icon font, no extra download).
 * Every icon is decorative (aria-hidden); the text next to it says what it means.
 */
final class LandingIcons
{
    private const PATHS = [
        'book' => '<path d="M4 5a2 2 0 0 1 2-2h12v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/><path d="M8 7h6"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'timer' => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2"/><path d="M9 2h6"/>',
        'plan' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="m9 15 2 2 4-4"/>',
        'offline' => '<path d="M2 8.8a15 15 0 0 1 4.2-2.6M22 8.8A15 15 0 0 0 10.7 5"/><path d="M5 12.6a10 10 0 0 1 3-1.8M19 12.6a10 10 0 0 0-4.8-2.4"/><path d="M8.5 16.4a5 5 0 0 1 7 0"/><circle cx="12" cy="20" r="1"/><path d="m2 2 20 20"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>',
        'trophy' => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'spark' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4"/><path d="m6 6 2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'play' => '<path d="M5 3.5v17a1 1 0 0 0 1.5.9l14-8.5a1 1 0 0 0 0-1.8l-14-8.5A1 1 0 0 0 5 3.5z"/>',
        'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'tick' => '<path d="m5 12 5 5 9-10"/>',
        'dash' => '<path d="M7 12h10"/>',
        'chat' => '<path d="M4 20l1.4-4.2A8 8 0 1 1 8.6 19z"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'id' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="12" r="2.5"/><path d="M14 10h4M14 14h3"/>',
        'pay' => '<rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'unlock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/>',
        // Subjects
        'mathematics' => '<path d="M18 5H7l6 7-6 7h11"/>',
        'physics' => '<circle cx="12" cy="12" r="1.5"/><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/>',
        'chemistry' => '<path d="M9 3h6M10 3v6L4.5 18.5A1.7 1.7 0 0 0 6 21h12a1.7 1.7 0 0 0 1.5-2.5L14 9V3"/><path d="M7 15h10"/>',
        'biology' => '<path d="M5 19c0-8 6-14 15-14 0 9-6 15-14 15"/><path d="M5 19 13 11"/>',
        'english' => '<path d="M4 18 8.5 6 13 18M5.5 14h6"/><path d="M15 12.5a3 3 0 1 1 0 5h4v-7"/>',
        'aptitude' => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z"/>',
        'geography' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'history' => '<path d="M6 3h12M6 21h12M7 3c0 5 10 5 10 9s-10 4-10 9M17 3c0 5-10 5-10 9s10 4 10 9"/>',
        'economics' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
    ];

    /**
     * Names of every icon (for tests).
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::PATHS);
    }

    /** Inline SVG for an icon; an unknown subject falls back to the book icon. */
    public static function svg(string $name, string $class = 'ic'): HtmlString
    {
        $paths = self::PATHS[$name] ?? self::PATHS['book'];

        return new HtmlString('<svg class="'.e($class).'" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>');
    }
}
