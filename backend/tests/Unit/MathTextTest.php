<?php

namespace Tests\Unit;

use App\Support\MathText;
use PHPUnit\Framework\TestCase;

/**
 * The same rules as the app's parseMath (app/lib/ui/widgets/math_text.dart), so Nova previews match.
 */
class MathTextTest extends TestCase
{
    public function test_plain_text(): void
    {
        $this->assertSame([['text', 'What is 2 + 2?']], MathText::parts('What is 2 + 2?'));
    }

    public function test_inline_and_block_maths(): void
    {
        $this->assertSame([
            ['text', 'Solve '],
            ['inline', '3x - 5 = 10'],
            ['text', ' now. '],
            ['block', 'x = \frac{15}{3}'],
            ['text', ' Done'],
        ], MathText::parts('Solve $3x - 5 = 10$ now. $$x = \frac{15}{3}$$ Done'));
    }

    public function test_escaped_dollar_is_literal(): void
    {
        $this->assertSame([['text', 'It costs $5']], MathText::parts('It costs \$5'));
    }

    public function test_unclosed_dollar_stays_text(): void
    {
        $this->assertSame([['text', 'Price $5 only']], MathText::parts('Price $5 only'));
    }

    public function test_empty_maths_stays_text(): void
    {
        $this->assertSame([['text', 'a $$ $$ b']], MathText::parts('a $$ $$ b'));
    }

    public function test_escaped_dollar_inside_maths_does_not_close_it(): void
    {
        $this->assertSame([['inline', 'a \$ b']], MathText::parts('$a \$ b$'));
    }

    public function test_maths_at_start_and_end_and_non_latin_text(): void
    {
        $this->assertSame([['inline', 'x']], MathText::parts('$x$'));
        $this->assertSame([['text', 'Ethiopia ኢትዮጵያ '], ['inline', 'π r^2']], MathText::parts('Ethiopia ኢትዮጵያ $π r^2$'));
    }

    public function test_html_escapes_text_and_keeps_tex_for_katex(): void
    {
        $html = MathText::html('<b>Area</b> $A = \pi r^2$ & $$x<y$$');

        $this->assertSame(
            '&lt;b&gt;Area&lt;/b&gt; <span class="guul-tex" data-display="0">A = \pi r^2</span> &amp; '
            .'<span class="guul-tex" data-display="1">x&lt;y</span>',
            $html,
        );
    }
}
