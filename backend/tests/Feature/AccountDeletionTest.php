<?php

namespace Tests\Feature;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\Battle;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\Referral;
use App\Models\User;
use App\Services\PremiumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_removes_the_student_and_every_row_about_them(): void
    {
        // A student with data in many tables, and another student who must not be touched.
        $other = User::factory()->create(['nickname' => 'Other']);
        $device = $this->postJson('/api/v1/auth/device', ['install_id' => (string) Str::uuid(), 'platform' => 'android', 'app_version' => '1.0.0+1'])->assertCreated();
        $token = (string) $device->json('token');
        $me = User::query()->where('student_id', $device->json('user.student_id'))->firstOrFail();
        $me->forceFill(['nickname' => 'Leaving', 'grade' => 12, 'stream' => 'natural', 'region' => 'somali'])->save();

        $q = Question::factory()->withOptions()->create(['chapter_id' => Chapter::factory()->create()->id]);
        $this->withToken($token)->postJson('/api/v1/attempts', [
            'client_uuid' => (string) Str::uuid(),
            'mode' => 'quick10',
            'started_at' => now()->subMinute()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'answers' => [['question_id' => $q->id, 'version' => 1, 'response' => 'A', 'time_ms' => 5000]],
        ])->assertCreated();
        $this->withToken($token)->putJson("/api/v1/bookmarks/{$q->id}")->assertSuccessful();
        Battle::query()->create(['code' => 'ABCDEF', 'key' => 'g12-natural', 'is_random' => false, 'question_ids' => [$q->id], 'creator_id' => $me->id, 'expires_at' => now()->addDay()]);
        Referral::query()->create(['inviter_id' => $other->id, 'invitee_id' => $me->id, 'status' => Referral::PENDING]);
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        app(PremiumService::class)->grant($me, PremiumPlan::Term, $helper, 'TX-1');

        $this->withToken($token)->deleteJson('/api/v1/me')->assertUnprocessable()->assertJsonPath('code', 'confirm_required');
        $this->withToken($token)->deleteJson('/api/v1/me?confirm=delete-my-account')->assertOk()->assertJsonPath('deleted', true);

        $this->assertNull(User::query()->find($me->id));
        // No row anywhere still points at the deleted student.
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            foreach (['user_id', 'creator_id', 'opponent_id', 'invitee_id', 'tokenable_id'] as $column) {
                if (Schema::hasColumn($name, $column)) {
                    $query = DB::table($name)->where($column, $me->id);
                    if ($column === 'tokenable_id') {
                        $query->where('tokenable_type', User::class);
                    }
                    $this->assertSame(0, $query->count(), "{$name}.{$column}");
                }
            }
        }
        $this->assertSame(0, Attempt::query()->count());
        $this->assertNotNull(User::query()->find($other->id));

        // The old token no longer works.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_staff_cannot_delete_themselves_here_and_guests_cannot_call_it(): void
    {
        $this->deleteJson('/api/v1/me?confirm=delete-my-account')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->staff(UserRole::SuperAdmin)->create());
        $this->deleteJson('/api/v1/me?confirm=delete-my-account')->assertForbidden()->assertJsonPath('code', 'staff_account');
    }
}
