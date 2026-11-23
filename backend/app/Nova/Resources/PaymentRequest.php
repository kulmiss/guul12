<?php

namespace App\Nova\Resources;

use App\Enums\PaymentRequestStatus;
use App\Enums\PremiumPlan;
use App\Models\AuditLog;
use App\Models\PaymentRequest as PaymentRequestModel;
use App\Models\User;
use App\Nova\Actions\ApprovePaymentRequest;
use App\Nova\Actions\RejectPaymentRequest;
use App\Nova\Fields\Show;
use App\Rules\NewPaymentReference;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Laravel\Nova\Query\Search\SearchableRelation;

/**
 * Payments made outside the app that wait for a check (DECISIONS #32).
 * Add one when a student messages; then Approve (gives Premium) or Reject. Never deleted.
 *
 * @extends \App\Nova\Resources\Resource<PaymentRequestModel>
 */
class PaymentRequest extends Resource
{
    /** @var class-string<PaymentRequestModel> */
    public static $model = PaymentRequestModel::class;

    public static $title = 'payment_ref';

    /** @return list<string|SearchableRelation> */
    public static function searchableColumns(): array
    {
        return ['payment_ref', new SearchableRelation('student', 'student_id')];
    }

    public static string $ability = 'manage-premium';

    /** @var list<string> */
    public static $with = ['student', 'creator', 'decider'];

    public static function label(): string
    {
        return 'Payment requests';
    }

    public static function singularLabel(): string
    {
        return 'Payment request';
    }

    public static function createButtonLabel(): string
    {
        return 'Add payment request';
    }

    /** Only waiting requests can be corrected; decided ones are history. */
    public function authorizedToUpdate(Request $request): bool
    {
        return parent::authorizedToUpdate($request)
            && $this->resource instanceof PaymentRequestModel
            && $this->resource->status === PaymentRequestStatus::Waiting;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        if ($request->filled('orderBy')) {
            return $query;
        }

        // Default order: waiting first (oldest waiting at the top), then the newest decisions.
        return $query
            ->orderByRaw('case when status = ? then 0 else 1 end', [PaymentRequestStatus::Waiting->value])
            ->orderByRaw('case when status = ? then created_at end asc', [PaymentRequestStatus::Waiting->value])
            ->orderByDesc('created_at');
    }

    /** Remembers who added the request and logs it. */
    public static function afterCreate(NovaRequest $request, Model $model): void
    {
        $staff = $request->user();
        if (! $model instanceof PaymentRequestModel || ! $staff instanceof User) {
            return;
        }
        $model->forceFill(['created_by' => $staff->id])->save();
        AuditLog::write($staff, 'payment_request.created', $model, [
            'student_id' => $model->student->student_id,
            'plan' => $model->plan->value,
            'payment_ref' => $model->payment_ref,
            'amount_birr' => $model->amount_birr,
        ]);
    }

    public static function afterUpdate(NovaRequest $request, Model $model): void
    {
        $staff = $request->user();
        if ($model instanceof PaymentRequestModel && $staff instanceof User) {
            AuditLog::write($staff, 'payment_request.edited', $model, ['changes' => $model->getChanges()]);
        }
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        $ignore = $this->resource instanceof PaymentRequestModel && $this->resource->exists ? $this->resource->id : null;

        return [
            ID::make()->hideFromIndex(),
            BelongsTo::make('Student', 'student', Student::class)
                ->searchable()
                ->rules('required')
                ->help('Type the student ID the student sent you (for example ST-7K3Q9).'),
            Select::make('Plan')
                ->options([PremiumPlan::Term->value => 'Term', PremiumPlan::Year->value => 'Year'])
                ->displayUsingLabels()
                ->rules('required', Rule::in([PremiumPlan::Term->value, PremiumPlan::Year->value])),
            Text::make('Payment reference', 'payment_ref')
                ->rules('required', 'string', 'max:128', new NewPaymentReference($ignore))
                ->fillUsing(fn ($request, $model, string $attribute, string $requestAttribute) => $model->{$attribute} = trim((string) $request->input($requestAttribute)))
                ->help('The reference on the receipt or bank message. The same reference cannot be used twice.')
                ->copyable(),
            Number::make('Amount (Br)', 'amount_birr')
                ->min(0)->max(100000)->step(1)
                ->rules('nullable', 'integer', 'min:0', 'max:100000')
                ->displayUsing(fn (?int $amount): string => $amount === null ? '—' : number_format($amount).' Br')
                ->help('Optional: what the student paid, to compare with the price.'),
            Textarea::make('Note')->rules('nullable', 'string', 'max:255')->alwaysShow(),
            Badge::make('Status', fn (PaymentRequestModel $r): string => $r->status->value)->map([
                PaymentRequestStatus::Waiting->value => 'warning',
                PaymentRequestStatus::Approved->value => 'success',
                PaymentRequestStatus::Rejected->value => 'danger',
            ])->labels([
                PaymentRequestStatus::Waiting->value => 'Waiting',
                PaymentRequestStatus::Approved->value => 'Premium given',
                PaymentRequestStatus::Rejected->value => 'Rejected',
            ]),
            ...Show::when('Added', 'created_at'),
            Text::make('Added by', fn (PaymentRequestModel $r): string => $r->creator->name ?? '—')->exceptOnForms()->hideFromIndex(),
            Text::make('Decided by', fn (PaymentRequestModel $r): string => $r->decider->name ?? '—')->exceptOnForms(),
            DateTime::make('Decided at', 'decided_at')->onlyOnDetail()->displayUsing(fn ($v): string => Show::full($v)),
            Text::make('Reject reason', 'reject_reason')->exceptOnForms()->hideFromIndex(),
            BelongsTo::make('Premium grant', 'premiumGrant', PremiumGrant::class)->exceptOnForms()->hideFromIndex()->nullable(),
        ];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [new ApprovePaymentRequest, new RejectPaymentRequest];
    }
}
