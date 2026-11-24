<?php

namespace Tests\Nova;

use App\Enums\UserRole;
use App\Models\LandingReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Website reviews in Nova: only real words with the student's permission reach the website.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class LandingReviewNovaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function review(array $changes = []): array
    {
        return [
            'name' => 'Hodan', 'grade' => 12, 'city' => 'Jigjiga', 'quote' => 'The mock exams helped me a lot.',
            'rating' => 5, 'permission_confirmed' => true, 'is_published' => true, 'sort' => 0, ...$changes,
        ];
    }

    public function test_a_review_cannot_be_shown_without_the_students_permission(): void
    {
        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)->postJson('/nova-api/website-reviews', $this->review(['permission_confirmed' => false]))
            ->assertStatus(422)->assertJsonValidationErrors(['is_published']);
        $this->assertSame(0, LandingReview::query()->count());

        // Saved but not shown yet: fine without permission.
        $this->actingAs($admin)->postJson('/nova-api/website-reviews', $this->review(['permission_confirmed' => false, 'is_published' => false]))
            ->assertCreated();

        $this->actingAs($admin)->postJson('/nova-api/website-reviews', $this->review())->assertCreated();
        $this->get('/')->assertSee('The mock exams helped me a lot.')->assertSee('What students say');
    }

    public function test_bad_values_are_refused(): void
    {
        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();
        $this->actingAs($admin)->postJson('/nova-api/website-reviews', $this->review([
            'name' => str_repeat('a', 41), 'grade' => 8, 'quote' => 'Short', 'rating' => 6,
        ]))->assertStatus(422)->assertJsonValidationErrors(['name', 'grade', 'quote', 'rating']);
        $this->assertSame(0, LandingReview::query()->count());
    }

    public function test_only_super_admins_manage_website_reviews(): void
    {
        foreach ([UserRole::PaymentsHelper, UserRole::ContentChecker] as $role) {
            $this->actingAs(User::factory()->staff($role)->create())->getJson('/nova-api/website-reviews')->assertForbidden();
            $this->actingAs(User::factory()->staff($role)->create())->postJson('/nova-api/website-reviews', $this->review())->assertForbidden();
        }
        $this->assertSame(0, LandingReview::query()->count());
    }
}
