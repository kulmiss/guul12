<?php

namespace Tests\Unit;

use App\Support\AuditLogDiff;
use PHPUnit\Framework\TestCase;

/**
 * The activity log shows "old → new" for changes: a short line in the list, a table on the
 * detail page. Everything is escaped (a nickname or a reason can contain HTML).
 */
class AuditLogDiffTest extends TestCase
{
    public function test_rows_pair_old_and_new_values_and_list_other_details(): void
    {
        $data = ['old' => ['status' => 'draft', 'difficulty' => 2], 'new' => ['status' => 'published', 'difficulty' => 3], 'reason' => 'checked'];

        $this->assertSame([
            ['field' => 'Status', 'old' => 'draft', 'new' => 'published'],
            ['field' => 'Difficulty', 'old' => '2', 'new' => '3'],
        ], AuditLogDiff::rows($data));
        $this->assertSame(['Reason' => 'checked'], AuditLogDiff::details($data));
    }

    public function test_values_are_readable(): void
    {
        $data = ['old' => ['ends_at' => null, 'is_active' => true, 'tags' => ['a', 'b']], 'new' => ['ends_at' => '2026-12-01', 'is_active' => false, 'tags' => []]];

        $this->assertSame([
            ['field' => 'Ends at', 'old' => '—', 'new' => '2026-12-01'],
            ['field' => 'Is active', 'old' => 'Yes', 'new' => 'No'],
            ['field' => 'Tags', 'old' => '["a","b"]', 'new' => '[]'],
        ], AuditLogDiff::rows($data));
    }

    public function test_a_key_only_on_one_side_still_shows(): void
    {
        $this->assertSame([['field' => 'Chapter id', 'old' => '—', 'new' => '9']],
            AuditLogDiff::rows(['old' => [], 'new' => ['chapter_id' => 9]]));
    }

    public function test_plain_entries_have_no_rows(): void
    {
        $this->assertSame([], AuditLogDiff::rows(null));
        $this->assertSame([], AuditLogDiff::rows(['student_id' => 'ST-1']));
        $this->assertSame([], AuditLogDiff::rows(['old' => 'x', 'new' => 'y']), 'old/new must be lists of fields');
        $this->assertSame(['Student id' => 'ST-1'], AuditLogDiff::details(['student_id' => 'ST-1']));
        $this->assertSame([], AuditLogDiff::details(null));
    }

    public function test_summary_is_one_short_line(): void
    {
        $this->assertSame('—', AuditLogDiff::summary(null));
        $this->assertSame('Student id: ST-1', AuditLogDiff::summary(['student_id' => 'ST-1']));
        $this->assertSame('Status: draft → published', AuditLogDiff::summary(['old' => ['status' => 'draft'], 'new' => ['status' => 'published']]));
        $this->assertSame('A: 1 → 2; B: 1 → 2 (+1 more)',
            AuditLogDiff::summary(['old' => ['a' => 1, 'b' => 1, 'c' => 1], 'new' => ['a' => 2, 'b' => 2, 'c' => 2]]));

        $long = AuditLogDiff::summary(['old' => ['note' => str_repeat('x', 300)], 'new' => ['note' => 'y']]);
        $this->assertLessThanOrEqual(120, mb_strlen($long));
        $this->assertStringEndsWith('…', $long);
    }

    public function test_html_is_escaped(): void
    {
        $html = AuditLogDiff::html(['old' => ['nickname' => '<b>Bad</b>'], 'new' => ['nickname' => 'Player 7'], 'reason' => '"rude" & <script>']);

        $this->assertStringContainsString('&lt;b&gt;Bad&lt;/b&gt;', $html);
        $this->assertStringContainsString('&quot;rude&quot; &amp; &lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('<del class="gd-old">', $html);
        $this->assertStringContainsString('<ins class="gd-new">Player 7</ins>', $html);
    }

    public function test_html_for_nothing_says_so(): void
    {
        $this->assertSame('<p class="guul-diff">No details.</p>', AuditLogDiff::html(null));
    }
}
