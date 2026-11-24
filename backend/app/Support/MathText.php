<?php

namespace App\Support;

/**
 * Splits question text into plain text and LaTeX, with exactly the app's rules
 * (app/lib/ui/widgets/math_text.dart): `$…$` inline, `$$…$$` on its own line, `\$` is a real
 * dollar sign, and a `$` with no closing `$` stays plain text.
 */
final class MathText
{
    /**
     * @return list<array{0: 'text'|'inline'|'block', 1: string}>
     */
    public static function parts(string $input): array
    {
        $chars = mb_str_split($input);
        $n = count($chars);
        $out = [];
        $buffer = '';
        $i = 0;

        while ($i < $n) {
            $ch = $chars[$i];

            if ($ch === '\\' && $i + 1 < $n && $chars[$i + 1] === '$') {
                $buffer .= '$';
                $i += 2;

                continue;
            }

            if ($ch === '$') {
                $isBlock = $i + 1 < $n && $chars[$i + 1] === '$';
                $length = $isBlock ? 2 : 1;
                $start = $i + $length;
                $end = self::findClosing($chars, $start, $isBlock);
                if ($end === -1) {
                    $buffer .= implode('', array_slice($chars, $i));

                    break;
                }
                $latex = trim(implode('', array_slice($chars, $start, $end - $start)));
                if ($latex === '') {
                    $buffer .= implode('', array_slice($chars, $i, $end + $length - $i));
                } else {
                    if ($buffer !== '') {
                        $out[] = ['text', $buffer];
                        $buffer = '';
                    }
                    $out[] = [$isBlock ? 'block' : 'inline', $latex];
                }
                $i = $end + $length;

                continue;
            }

            $buffer .= $ch;
            $i++;
        }

        if ($buffer !== '') {
            $out[] = ['text', $buffer];
        }

        return $out;
    }

    /**
     * Safe HTML: text is escaped; maths becomes <span class="guul-tex"> holding the escaped LaTeX,
     * which Nova draws with KaTeX (resources/nova/guul-nova.js). Line breaks are kept.
     */
    public static function html(string $input): string
    {
        $html = '';
        foreach (self::parts($input) as [$type, $value]) {
            $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= $type === 'text'
                ? nl2br($escaped, false)
                : '<span class="guul-tex" data-display="'.($type === 'block' ? '1' : '0').'">'.$escaped.'</span>';
        }

        return $html;
    }

    /** @param  list<string>  $chars */
    private static function findClosing(array $chars, int $from, bool $block): int
    {
        $n = count($chars);
        $i = $from;
        while ($i < $n) {
            if ($chars[$i] === '\\') {
                $i += 2;

                continue;
            }
            if ($chars[$i] === '$') {
                $double = $i + 1 < $n && $chars[$i + 1] === '$';
                if ($block) {
                    if ($double) {
                        return $i;
                    }
                } else {
                    // A single $ must not be the first half of $$.
                    return $double ? -1 : $i;
                }
            }
            $i++;
        }

        return -1;
    }
}
