<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Stats\StatsCache;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * Dashboard numbers come from the cache; only the scheduled refresh (guul:stats) queries the tables.
 */
class StatsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cached_number_needs_no_database_query_and_changes_only_after_a_refresh(): void
    {
        $cache = app(StatsCache::class);
        User::factory()->count(2)->create();

        $this->assertSame(2, $cache->get('new_students', '30')['value']);
        $this->assertNotNull($cache->updatedAt('new_students', '30'));

        User::factory()->create();
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries++;
        });
        $this->assertSame(2, $cache->get('new_students', '30')['value'], 'served from the cache');
        $this->assertSame(0, $queries, 'opening a card runs no database query');

        $command = $this->artisan('guul:stats');
        $this->assertInstanceOf(PendingCommand::class, $command);
        // Run now (a pending test command otherwise runs only when the variable goes away).
        $command->expectsOutputToContain('Dashboard numbers refreshed')->assertSuccessful()->run();
        $this->assertSame(3, $cache->get('new_students', '30')['value']);
    }

    public function test_refresh_all_keeps_every_number_for_every_range(): void
    {
        $ranged = count(array_filter(StatsCache::definitions(), fn (array $d): bool => $d[0]));
        $plain = count(StatsCache::definitions()) - $ranged;

        $this->assertSame($ranged * count(StatsCache::RANGES) + $plain, app(StatsCache::class)->refreshAll());
    }

    public function test_unknown_numbers_and_ranges(): void
    {
        $cache = app(StatsCache::class);
        // An unknown range falls back to 30 days; an unknown number is a programming error.
        $this->assertSame($cache->get('new_students', '30'), $cache->get('new_students', 'nonsense'));
        $this->expectException(\InvalidArgumentException::class);
        $cache->get('no_such_number');
    }
}
