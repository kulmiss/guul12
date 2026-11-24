<?php

namespace Tests\Unit;

use App\Support\CsvCell;
use PHPUnit\Framework\TestCase;

class CsvCellTest extends TestCase
{
    public function test_text_that_starts_like_a_formula_is_shown_not_run(): void
    {
        foreach (['=1+1', '+252', '-2', '@SUM(A1)', "\tx", "\rx"] as $text) {
            $this->assertSame("'".$text, CsvCell::safe($text), json_encode($text) ?: '');
        }
    }

    public function test_normal_values_stay_the_same(): void
    {
        $this->assertSame('Somali', CsvCell::safe('Somali'));
        $this->assertSame('a=b', CsvCell::safe('a=b'));
        $this->assertSame('', CsvCell::safe(null));
        $this->assertSame('', CsvCell::safe(''));
        $this->assertSame(12, CsvCell::safe(12));
        $this->assertSame(-2.5, CsvCell::safe(-2.5), 'real numbers are numbers, not formulas');
        $this->assertSame(['a' => "'=x", 'b' => 3], CsvCell::row(['a' => '=x', 'b' => 3]));
    }
}
