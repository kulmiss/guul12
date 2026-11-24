<?php

namespace App\Nova\Resources;

use App\Models\LandingReview as LandingReviewModel;
use Closure;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Real student reviews for the website's "What students say" section. Never invent a review:
 * only real words from a real student who agreed to have them shown.
 *
 * @extends \App\Nova\Resources\Resource<LandingReviewModel>
 */
class LandingReview extends Resource
{
    /** @var class-string<LandingReviewModel> */
    public static $model = LandingReviewModel::class;

    public static $globallySearchable = false;

    public static $title = 'name';

    /** @var array<int, string> */
    public static $search = ['name', 'city', 'quote'];

    public static string $ability = 'manage-settings';

    public static function label(): string
    {
        return 'Website reviews';
    }

    public static function singularLabel(): string
    {
        return 'Website review';
    }

    public static function uriKey(): string
    {
        return 'website-reviews';
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('First name', 'name')->rules('required', 'string', 'max:40')
                ->help('First name only (or first name + initial). No surname, phone or school.'),
            Select::make('Grade', 'grade')->options([9 => 'Grade 9', 10 => 'Grade 10', 11 => 'Grade 11', 12 => 'Grade 12'])
                ->displayUsingLabels()->rules('required', 'integer', 'between:9,12'),
            Text::make('City', 'city')->nullable()->rules('nullable', 'string', 'max:60'),
            Textarea::make('What the student said', 'quote')->rules('required', 'string', 'min:10', 'max:300')
                ->alwaysShow()->help('The student\'s own words, 10–300 characters. Fix spelling only.'),
            Select::make('Stars', 'rating')->options([5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★'])
                ->displayUsingLabels()->rules('required', 'integer', 'between:1,5'),
            Boolean::make('Student gave permission', 'permission_confirmed')
                ->help('Tick only when the student agreed that these words, their first name, grade and city appear on the website.'),
            Boolean::make('Show on the website', 'is_published')
                ->rules(fn (NovaRequest $request): Closure => function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN) && ! $request->boolean('permission_confirmed')) {
                        $fail('Tick "Student gave permission" before showing a review on the website.');
                    }
                }),
            Number::make('Order', 'sort')->min(0)->step(1)->default(0)->rules('required', 'integer', 'min:0')
                ->help('Smaller numbers come first. At most '.LandingReviewModel::SHOWN.' reviews are shown.'),
        ];
    }
}
