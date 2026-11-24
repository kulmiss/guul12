<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * MySQL refuses table, column and index names longer than 64 characters. Tests run on SQLite,
 * which allows them, so this check catches them before the owner's Laragon (MySQL) does.
 */
class DatabaseNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_table_column_or_index_name_is_longer_than_mysql_allows(): void
    {
        $names = [];
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select("select type, name from sqlite_master where type in ('table', 'index') and name not like 'sqlite_%'") as $row) {
                $names[] = $row->name;
                if ($row->type === 'table') {
                    foreach (DB::select("pragma table_info('{$row->name}')") as $column) {
                        $names[] = $column->name;
                    }
                }
            }
        } else {
            // MySQL / MariaDB: the server already refuses long names; list them the same way anyway.
            foreach (['tables' => 'table_name', 'columns' => 'column_name', 'statistics' => 'index_name'] as $view => $field) {
                foreach (DB::select("select distinct {$field} as name from information_schema.{$view} where table_schema = database()") as $row) {
                    $names[] = $row->name;
                }
            }
        }

        $tooLong = array_values(array_filter($names, fn (string $name): bool => strlen($name) > 64));

        $this->assertGreaterThan(50, count($names));
        $this->assertSame([], $tooLong);
    }
}
