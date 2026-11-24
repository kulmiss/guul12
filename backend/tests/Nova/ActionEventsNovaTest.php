<?php

namespace Tests\Nova;

use App\Enums\UserRole;
use App\Models\User;
use App\Nova\Resources\ActionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Nova\Nova;
use Tests\TestCase;

/**
 * Nova keeps its own record of every action run (who gave Premium to whom, the reason typed …).
 * Only super admins may read it: the other roles must not see work outside their job.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class ActionEventsNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    public function test_only_super_admins_read_novas_action_record(): void
    {
        $this->assertSame(ActionLog::class, Nova::actionResource());

        foreach ([UserRole::ContentChecker, UserRole::PaymentsHelper] as $role) {
            $staff = User::factory()->staff($role)->create();
            $this->actingAs($staff)->getJson('/nova-api/action-events')->assertForbidden();
            $this->actingAs($staff)->get('/nova/resources/action-events')->assertForbidden();
        }
        $boss = User::factory()->staff(UserRole::SuperAdmin)->create();
        $this->actingAs($boss)->getJson('/nova-api/action-events')->assertOk();
    }

    public function test_one_entry_and_the_filter_list_are_refused_too(): void
    {
        $boss = User::factory()->staff(UserRole::SuperAdmin)->create();
        $student = User::factory()->create();
        $id = DB::table('action_events')->insertGetId([
            'batch_id' => (string) Str::uuid(), 'user_id' => $boss->id, 'name' => 'Give Premium',
            'actionable_type' => User::class, 'actionable_id' => $student->id,
            'target_type' => User::class, 'target_id' => $student->id,
            'model_type' => User::class, 'model_id' => $student->id,
            'fields' => '{"reason":"paid"}', 'status' => 'finished', 'exception' => '',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $this->actingAs($checker)->getJson("/nova-api/action-events/{$id}")->assertForbidden();
        $this->actingAs($checker)->getJson('/nova-api/action-events/filters')->assertForbidden();
        $this->actingAs($checker)->getJson('/nova-api/action-events/cards')->assertForbidden();
        $this->actingAs($boss)->getJson("/nova-api/action-events/{$id}")->assertOk();
    }
}
