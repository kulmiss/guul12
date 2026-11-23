<?php

namespace App\Models;

use App\Enums\PaymentRequestStatus;
use App\Enums\PremiumPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment made outside the app, waiting for a staff member to check it (DECISIONS #32).
 *
 * @property int $id
 * @property int $user_id
 * @property PremiumPlan $plan
 * @property string $payment_ref
 * @property int|null $amount_birr
 * @property string|null $note
 * @property PaymentRequestStatus $status
 * @property int|null $created_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $reject_reason
 * @property int|null $premium_grant_id
 * @property Carbon $created_at
 * @property-read User $student
 * @property-read User|null $creator
 * @property-read User|null $decider
 * @property-read PremiumGrant|null $premiumGrant
 */
class PaymentRequest extends Model
{
    protected $fillable = ['user_id', 'plan', 'payment_ref', 'amount_birr', 'note', 'status', 'created_by', 'decided_by', 'decided_at', 'reject_reason', 'premium_grant_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => PremiumPlan::class,
            'status' => PaymentRequestStatus::class,
            'amount_birr' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<PremiumGrant, $this>
     */
    public function premiumGrant(): BelongsTo
    {
        return $this->belongsTo(PremiumGrant::class);
    }
}
