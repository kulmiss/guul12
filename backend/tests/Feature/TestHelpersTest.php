<?php

namespace Tests\Feature;

use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

/**
 * Our own test helpers behave as promised.
 */
class TestHelpersTest extends TestCase
{
    public function test_same_json_ignores_object_key_order_like_mysql_8_but_not_values_or_list_order(): void
    {
        // MySQL 8 gives JSON keys back in its own order (shorter keys first).
        $this->assertSameJson(['old' => ['status' => 'beta'], 'new' => ['status' => 'published']],
            ['new' => ['status' => 'published'], 'old' => ['status' => 'beta']]);
        $this->assertSameJson(['b' => ['y' => 1, 'x' => [3, 1]], 'a' => null], ['a' => null, 'b' => ['x' => [3, 1], 'y' => 1]]);

        foreach ([
            [['a' => 1], ['a' => 2]],
            [['list' => [1, 2]], ['list' => [2, 1]]],
            [['a' => 1], null],
        ] as [$expected, $actual]) {
            try {
                $this->assertSameJson($expected, $actual);
                $failed = false;
            } catch (AssertionFailedError) {
                $failed = true;
            }
            $this->assertTrue($failed, 'a real difference must still fail: '.json_encode($actual));
        }
    }
}
