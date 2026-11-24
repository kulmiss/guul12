<?php

namespace Tests\Nova;

use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\Battle;
use App\Models\Mock;
use App\Models\Question;
use App\Models\Referral;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mocks, battles and referrals in Nova: read-only lists for the right roles, no N+1 queries,
 * search, counts and badges, and nothing secret (battle keys, question lists) shown.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class ActivityResourcesNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    private int $seq = 0;

    private function makeMock(?Subject $subject = null, int $questions = 2): Mock
    {
        $subject ??= Subject::factory()->create();
        $mock = Mock::query()->create([
            'uid' => 'm-'.(++$this->seq), 'subject_id' => $subject->id, 'grade' => 12, 'number' => $this->seq,
            'title' => "Biology mock {$this->seq}", 'minutes' => 60, 'access' => 'premium', 'is_active' => true,
        ]);
        foreach (Question::factory()->count($questions)->create() as $i => $question) {
            DB::table('mock_questions')->insert(['mock_id' => $mock->id, 'question_id' => $question->id, 'sort' => $i + 1]);
        }

        return $mock;
    }

    private function battle(?User $opponent = null, bool $expired = false): Battle
    {
        return Battle::query()->create([
            'code' => strtoupper(Str::random(6)), 'key' => 'g12-natural', 'subject_id' => null, 'is_random' => false,
            'question_ids' => [1, 2, 3], 'creator_id' => User::factory()->create(['nickname' => 'Hodan'.(++$this->seq)])->id,
            'opponent_id' => $opponent?->id, 'expires_at' => $expired ? now()->subDay() : now()->addDay(),
        ]);
    }

    private function referral(string $status = Referral::PENDING): Referral
    {
        return Referral::query()->create([
            'inviter_id' => User::factory()->create()->id, 'invitee_id' => User::factory()->create()->id,
            'status' => $status, 'valid_at' => $status === Referral::VALID ? now() : null,
        ]);
    }

    private function staff(UserRole $role): User
    {
        return User::factory()->staff($role)->create();
    }

    /** @return array<string, mixed> field name => what staff see (badge label, displayed text), or the raw value with $raw */
    private function fields(User $user, string $uri, bool $raw = false): array
    {
        $json = $this->actingAs($user)->getJson($uri)->assertOk()->json();
        $this->assertIsArray($json);
        $out = [];
        foreach ($json['resource']['fields'] ?? $json['resources'][0]['fields'] ?? [] as $field) {
            $out[(string) $field['name']] = $raw ? $field['value'] : ($field['component'] === 'badge-field' ? $field['label'] : ($field['displayedAs'] ?? $field['value']));
        }

        return $out;
    }

    public function test_each_list_opens_only_for_the_right_roles(): void
    {
        $this->makeMock();
        $this->battle();
        $this->referral();
        $allowed = [
            'mocks' => [UserRole::SuperAdmin, UserRole::ContentChecker],
            'battles' => [UserRole::SuperAdmin, UserRole::ContentChecker],
            'referrals' => [UserRole::SuperAdmin, UserRole::PaymentsHelper],
        ];
        foreach ($allowed as $uri => $roles) {
            foreach ([UserRole::SuperAdmin, UserRole::PaymentsHelper, UserRole::ContentChecker] as $role) {
                $this->actingAs($this->staff($role))->getJson("/nova-api/{$uri}")
                    ->assertStatus(in_array($role, $roles, true) ? 200 : 403);
            }
        }
    }

    public function test_they_are_read_only_even_for_the_super_admin(): void
    {
        $admin = $this->staff(UserRole::SuperAdmin);
        $ids = ['mocks' => $this->makeMock()->id, 'battles' => $this->battle()->id, 'referrals' => $this->referral()->id];

        foreach ($ids as $uri => $id) {
            $this->actingAs($admin)->getJson("/nova-api/{$uri}/creation-fields")->assertForbidden();
            $this->actingAs($admin)->putJson("/nova-api/{$uri}/{$id}", ['title' => 'x', 'status' => 'valid'])->assertForbidden();
            $this->actingAs($admin)->deleteJson("/nova-api/{$uri}?resources[]={$id}");
            $this->assertDatabaseHas($uri, ['id' => $id]);

            $resource = $this->actingAs($admin)->getJson("/nova-api/{$uri}/{$id}")->assertOk()->json('resource');
            $this->assertFalse($resource['authorizedToUpdate'], $uri);
            $this->assertFalse($resource['authorizedToDelete'], $uri);
        }
        $this->assertSame('Biology mock 1', Mock::query()->findOrFail($ids['mocks'])->title);
        $this->assertSame(Referral::PENDING, Referral::query()->findOrFail($ids['referrals'])->status);
    }

    public function test_lists_have_no_n_plus_one_queries(): void
    {
        $admin = $this->staff(UserRole::SuperAdmin);
        $makers = [
            'mocks' => fn () => $this->makeMock(),
            'battles' => fn () => $this->battle(User::factory()->create()),
            'referrals' => fn () => $this->referral(Referral::VALID),
        ];
        foreach ($makers as $uri => $make) {
            $make();
            $make();
            // One request first: caches (settings, branding) are filled once, as in normal use.
            $this->queries($admin, "/nova-api/{$uri}");
            $few = $this->queries($admin, "/nova-api/{$uri}");
            for ($i = 0; $i < 12; $i++) {
                $make();
            }
            $many = $this->queries($admin, "/nova-api/{$uri}");
            $this->assertSame($few, $many, "{$uri}: {$few} queries with a few rows, {$many} with more rows (N+1)");
        }
    }

    private function queries(User $user, string $uri): int
    {
        $count = 0;
        DB::listen(function (QueryExecuted $query) use (&$count): void {
            $count++;
        });
        $this->actingAs($user)->getJson($uri)->assertOk();

        return $count;
    }

    public function test_mocks_show_subject_question_count_and_access(): void
    {
        $mock = $this->makeMock(Subject::factory()->create(['name' => 'Biology']), questions: 3);
        $checker = $this->staff(UserRole::ContentChecker);

        $row = $this->fields($checker, '/nova-api/mocks');
        $this->assertSame('Biology mock 1', $row['Title']);
        $this->assertSame(3, $this->fields($checker, '/nova-api/mocks', raw: true)['Questions']);
        $this->assertSame('Premium', $row['Access']);
        $this->assertSame('Grade 12', $row['Grade']);

        $found = $this->actingAs($checker)->getJson('/nova-api/mocks?search=m-1')->assertOk()->json('resources');
        $this->assertIsArray($found);
        $this->assertCount(1, $found);
        $this->assertSame($mock->id, $found[0]['id']['value']);
    }

    public function test_battles_show_status_and_nicknames_but_no_secrets(): void
    {
        $checker = $this->staff(UserRole::ContentChecker);
        $opponent = User::factory()->create(['nickname' => 'Ayaan']);
        $played = $this->battle($opponent);
        Attempt::query()->create(['user_id' => $played->creator_id, 'client_uuid' => (string) Str::uuid(), 'mode' => 'battle', 'battle_id' => $played->id,
            'started_at' => now()->subMinutes(5), 'finished_at' => now(), 'total' => 3, 'correct' => 2]);
        $waiting = $this->battle();
        $expired = $this->battle(expired: true);

        $detail = $this->fields($checker, "/nova-api/battles/{$played->id}");
        $this->assertSame('Matched', $detail['Status']);
        $this->assertSame('Ayaan', $detail['Opponent']);
        $raw = $this->fields($checker, "/nova-api/battles/{$played->id}", raw: true);
        $this->assertSame(3, $raw['Questions']);
        $this->assertSame(1, $raw['Finished']);
        $json = (string) json_encode($this->actingAs($checker)->getJson("/nova-api/battles/{$played->id}")->json());
        $this->assertStringNotContainsString('g12-natural', $json, 'the matching key is not shown');
        $this->assertStringNotContainsString($opponent->student_id, $json, 'content checkers do not see student IDs');

        $this->assertSame('Waiting', $this->fields($checker, "/nova-api/battles/{$waiting->id}")['Status']);
        $this->assertSame('Expired', $this->fields($checker, "/nova-api/battles/{$expired->id}")['Status']);

        $found = $this->actingAs($checker)->getJson('/nova-api/battles?search='.strtolower($waiting->code))->assertOk()->json('resources');
        $this->assertIsArray($found);
        $this->assertSame([$waiting->id], array_map(fn (array $r): int => $r['id']['value'], $found));
    }

    public function test_referrals_show_both_students_and_can_be_found_by_student_id(): void
    {
        $helper = $this->staff(UserRole::PaymentsHelper);
        $referral = $this->referral(Referral::VALID);
        $this->referral();

        $detail = $this->fields($helper, "/nova-api/referrals/{$referral->id}");
        $this->assertSame('Counted', $detail['Status']);
        $this->assertSame($referral->inviter->student_id, $detail['Invited by']);
        $this->assertSame($referral->invitee->student_id, $detail['New student']);

        foreach ([$referral->inviter->student_id, $referral->invitee->student_id] as $id) {
            $found = $this->actingAs($helper)->getJson('/nova-api/referrals?search='.$id)->assertOk()->json('resources');
            $this->assertIsArray($found);
            $this->assertSame([$referral->id], array_map(fn (array $r): int => $r['id']['value'], $found));
        }
    }
}
