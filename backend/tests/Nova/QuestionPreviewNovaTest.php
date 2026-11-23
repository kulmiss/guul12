<?php

namespace Tests\Nova;

use App\Enums\UserRole;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checkers see each question as students do: the detail page has a Preview panel, and Nova loads
 * KaTeX (served from our own site, no CDN) plus our script that draws the maths.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class QuestionPreviewNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    public function test_question_detail_has_a_safe_preview_panel(): void
    {
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $question = Question::factory()->withOptions()->create(['stem' => 'Is $\\frac{1}{2}$ <img src=x onerror=alert(1)> half?']);

        $fields = $this->actingAs($checker)->getJson("/nova-api/questions/{$question->id}")->assertOk()->json('resource.fields');
        $this->assertIsArray($fields);
        $preview = collect($fields)->firstWhere('name', 'Preview');
        $this->assertIsArray($preview, 'the detail page has a Preview field');
        $this->assertSame('Preview', $preview['panel']);
        $this->assertTrue($preview['asHtml']);
        $this->assertStringContainsString('<span class="guul-tex" data-display="0">\\frac{1}{2}</span>', $preview['value']);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $preview['value']);
        $this->assertStringNotContainsString('<img src=x', $preview['value']);
        $this->assertSame(1, substr_count($preview['value'], 'title="Right answer"'));

        // Only on the detail page: not in the list and not in the edit form.
        $index = $this->actingAs($checker)->getJson('/nova-api/questions')->assertOk()->json('resources.0.fields');
        $this->assertIsArray($index);
        $this->assertNull(collect($index)->firstWhere('name', 'Preview'));
        $form = $this->actingAs($checker)->getJson("/nova-api/questions/{$question->id}/update-fields")->assertOk()->json('fields');
        $this->assertIsArray($form);
        $this->assertNull(collect($form)->firstWhere('name', 'Preview'));
    }

    public function test_nova_loads_katex_from_our_site_and_draws_the_maths(): void
    {
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();

        $page = $this->actingAs($checker)->get('/nova/dashboards/main')->assertOk();
        $page->assertSee(asset('katex/katex.min.js'), false);
        $page->assertSee(asset('katex/katex.min.css'), false);

        $this->actingAs($checker)->get('/nova-api/scripts/guul-nova')->assertOk()
            ->assertSee('.guul-tex:not([data-done])', false)
            ->assertSee('window.katex.render', false)
            ->assertSee('throwOnError: false', false);

        // The preview has its own small stylesheet (Nova's CSS has only the classes Nova itself uses).
        $this->actingAs($checker)->get('/nova-api/styles/guul-nova')->assertOk()
            ->assertSee('.guul-preview .gp-right', false)
            ->assertSee('.dark .guul-preview .gp-right', false);

        foreach (['katex.min.js', 'katex.min.css', 'LICENSE'] as $file) {
            $this->assertFileExists(public_path("katex/{$file}"));
        }
    }
}
