<?php

namespace Tests\Nova;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The grouped left menu: each role sees only its sections, and badges show open work.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class NovaMenuTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    /** @return list<array<string, mixed>> */
    private function menuFor(User $user): array
    {
        $this->setUpResetsNovaPageData();
        $html = (string) $this->actingAs($user)->get('/nova/dashboards/main')->assertOk()->getContent();
        if (preg_match('/data-page="([^"]+)"/', $html, $match) !== 1) {
            $this->fail('The Nova page has no page data.');
        }
        $page = json_decode(html_entity_decode($match[1]), true);
        $this->assertIsArray($page);
        $menu = $page['props']['novaConfig']['mainMenu'] ?? null;
        $this->assertIsArray($menu);

        return array_values($menu);
    }

    /** @return list<string> */
    private function sectionsFor(UserRole $role): array
    {
        return array_map(fn (array $section): string => (string) $section['name'], $this->menuFor(User::factory()->staff($role)->create()));
    }

    public function test_each_role_sees_only_its_sections(): void
    {
        $this->assertSame(['Dashboards', 'Students & Premium', 'Content', 'Community', 'Settings', 'System'], $this->sectionsFor(UserRole::SuperAdmin));
        $this->assertSame(['Dashboards', 'Students & Premium'], $this->sectionsFor(UserRole::PaymentsHelper));
        $this->assertSame(['Dashboards', 'Content', 'Community'], $this->sectionsFor(UserRole::ContentChecker));

        // Each role sees the Overview and its own dashboard only.
        $dashboards = fn (UserRole $role): array => array_column($this->menuFor(User::factory()->staff($role)->create())[0]['items'], 'name');
        $this->assertSame(['Overview', 'Payments', 'Content'], $dashboards(UserRole::SuperAdmin));
        $this->assertSame(['Overview', 'Payments'], $dashboards(UserRole::PaymentsHelper));
        $this->assertSame(['Overview', 'Content'], $dashboards(UserRole::ContentChecker));
    }

    public function test_open_reports_show_a_badge_and_none_when_zero(): void
    {
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $before = $this->reportsItem($checker);
        $this->assertNull($before['badge']);

        $question = Question::factory()->create()->refresh();
        foreach ([ReportStatus::Open, ReportStatus::Open, ReportStatus::Accepted] as $status) {
            QuestionReport::query()->create([
                'question_id' => $question->id, 'user_id' => User::factory()->create()->id,
                'question_version' => $question->version, 'reason' => 'wrong_answer', 'status' => $status,
            ]);
        }

        $after = $this->reportsItem($checker);
        $badge = $after['badge'];
        $this->assertIsArray($badge);
        $this->assertSame('2', $badge['value']);
        $this->assertStringContainsString('yellow', (string) $badge['typeClass']);
    }

    /** @return array<string, mixed> */
    private function reportsItem(User $user): array
    {
        foreach ($this->menuFor($user) as $section) {
            foreach ($section['items'] as $item) {
                if ($item['name'] === 'Question Reports') {
                    return $item;
                }
            }
        }
        $this->fail('No "Question Reports" item in the menu.');
    }
}
