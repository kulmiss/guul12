<?php

namespace Tests\Nova;

use App\Enums\UserRole;
use App\Models\User;
use App\Nova\EmptyStates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Nova\Nova;
use Tests\TestCase;

/**
 * Empty lists say what belongs there and how it gets filled (for example "Students appear here
 * after they open the app"), instead of only Nova's "No … matched the given criteria".
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class EmptyStatesNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    public function test_every_list_has_a_help_line(): void
    {
        $boss = User::factory()->staff(UserRole::SuperAdmin)->create();
        $this->actingAs($boss)->get('/nova/dashboards/main')->assertOk();

        $keys = collect(Nova::$resources)->map(fn (string $resource): string => $resource::uriKey())->sort()->values()->all();
        $help = EmptyStates::all();
        $this->assertSame($keys, collect(array_keys($help))->sort()->values()->all(), 'one line per list, no extras');
        foreach ($help as $key => $line) {
            $this->assertMatchesRegularExpression('/^[A-Z].{10,160}\.$/u', $line, $key);
            $this->assertDoesNotMatchRegularExpression('/\b(TODO|price|birr)\b/i', $line, $key);
        }
    }

    public function test_the_page_gets_the_lines_and_the_script_uses_them(): void
    {
        $boss = User::factory()->staff(UserRole::SuperAdmin)->create();

        $page = $this->actingAs($boss)->get('/nova/resources/students')->assertOk()->getContent();
        $this->assertStringContainsString('"guulEmptyHelp":', (string) $page);
        $this->assertStringContainsString(json_encode(EmptyStates::all()['students'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'x', (string) $page);

        $script = (string) file_get_contents(resource_path('nova/guul-nova.js'));
        $this->assertStringContainsString("Nova.config('guulEmptyHelp')", $script);
        $this->assertStringContainsString('-empty-dialog', $script);
    }
}
