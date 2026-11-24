<?php

namespace Tests\Nova;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Activity log in Nova: the list shows a short "old → new" line, the detail page a Before/After
 * table (escaped), and nobody can create, edit or delete log entries.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class ActivityLogDiffNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function field(array $fields, string $name, string $key = 'value'): mixed
    {
        foreach ($fields as $field) {
            if (($field['name'] ?? null) === $name) {
                return $field[$key] ?? null;
            }
        }

        return null;
    }

    public function test_list_and_detail_show_old_and_new(): void
    {
        $boss = User::factory()->staff(UserRole::SuperAdmin)->create();
        $student = User::factory()->create();
        $log = AuditLog::change($boss, 'nickname.reset', $student, ['nickname' => '<b>Bad</b>'], ['nickname' => 'Player 7'], ['reason' => 'rude']);

        $row = $this->actingAs($boss)->getJson('/nova-api/audit-logs')->assertOk()->json('resources.0.fields');
        $this->assertSame('Nickname: <b>Bad</b> → Player 7', $this->field($row, 'Change'), 'Nova escapes plain text itself');

        $detail = $this->actingAs($boss)->getJson("/nova-api/audit-logs/{$log->id}")->assertOk()->json('resource.fields');
        $html = (string) $this->field($detail, 'Changes');
        $this->assertStringContainsString('<del class="gd-old">&lt;b&gt;Bad&lt;/b&gt;</del>', $html);
        $this->assertStringContainsString('<ins class="gd-new">Player 7</ins>', $html);
        $this->assertStringContainsString('<dt>Reason</dt><dd>rude</dd>', $html);
        $this->assertTrue($this->field($detail, 'Changes', 'asHtml'));
    }

    public function test_the_log_cannot_be_changed(): void
    {
        $boss = User::factory()->staff(UserRole::SuperAdmin)->create();
        $log = AuditLog::write($boss, 'cache.refreshed');

        $this->actingAs($boss)->getJson('/nova-api/audit-logs/creation-fields')->assertForbidden();
        $this->actingAs($boss)->putJson("/nova-api/audit-logs/{$log->id}", ['action' => 'x'])->assertForbidden();
        $this->actingAs($boss)->deleteJson('/nova-api/audit-logs', ['resources' => [$log->id]])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'cache.refreshed']);
    }
}
