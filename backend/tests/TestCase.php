<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs an artisan command in a way the static analyser understands.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function command(string $name, array $parameters = []): PendingCommand
    {
        $command = $this->artisan($name, $parameters);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    /**
     * Compares data read back from a JSON column. MySQL 8 stores JSON object keys in its own order
     * (MariaDB and SQLite keep ours), so keys are sorted first; lists keep their order.
     *
     * @param  array<mixed>  $expected
     */
    protected function assertSameJson(array $expected, mixed $actual, string $message = ''): void
    {
        $this->assertIsArray($actual, $message);
        $this->assertSame(self::sortKeys($expected), self::sortKeys($actual), $message);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function sortKeys(array $value): array
    {
        $value = array_map(fn (mixed $v): mixed => is_array($v) ? self::sortKeys($v) : $v, $value);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
