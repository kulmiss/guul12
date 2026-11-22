<?php

namespace App\Models;

use App\Enums\PremiumPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property PremiumPlan $plan
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $payment_ref
 * @property int|null $granted_by
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property string|null $note
 * @property-read User $user
 */
class PremiumGrant extends Model
{
    protected $fillable = ['user_id', 'plan', 'starts_at', 'ends_at', 'payment_ref', 'granted_by', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => PremiumPlan::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiveAt(Builder $query, Carbon $moment): Builder
    {
        return $query->whereNull('revoked_at')
            ->where('starts_at', '<=', $moment)
            ->where('ends_at', '>', $moment);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
