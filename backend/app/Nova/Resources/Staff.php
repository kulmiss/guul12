<?php

namespace App\Nova\Resources;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\StudentIdGenerator;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Staff accounts (super admin only).
 *
 * @extends \App\Nova\Resources\Resource<User>
 */
class Staff extends Resource
{
    /** @var class-string<User> */
    public static $model = User::class;

    public static $title = 'name';

    public function subtitle(): ?string
    {
        $row = $this->resource;
        if ($row === null) {
            return null;
        }

        return self::words($row->role);
    }

    /** @var array<int, string> */
    public static $search = ['name', 'email'];

    public static string $ability = 'manage-settings';

    public static function label(): string
    {
        return 'Staff';
    }

    public static function uriKey(): string
    {
        return 'staff';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->where('role', '!=', UserRole::Student->value);
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Name')->rules('required', 'max:191'),
            Text::make('Email')->rules('required', 'email', 'max:191')
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),
            Select::make('Role')->options([
                UserRole::SuperAdmin->value => 'Super admin',
                UserRole::PaymentsHelper->value => 'Payments helper',
                UserRole::ContentChecker->value => 'Content checker',
            ])->rules('required')->displayUsingLabels()
                ->resolveUsing(fn ($value) => $value instanceof UserRole ? $value->value : $value),
            Password::make('Password')->onlyOnForms()
                ->creationRules('required', 'min:12')
                ->updateRules('nullable', 'min:12'),
        ];
    }

    /**
     * Staff rows still need a unique student_id value (never shown or used).
     *
     * @param  User  $model
     */
    public static function fill(NovaRequest $request, $model): array
    {
        $model->student_id = app(StudentIdGenerator::class)->generate();

        return parent::fill($request, $model);
    }
}
