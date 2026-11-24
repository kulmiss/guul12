<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inviter_id
 * @property int $invitee_id
 * @property string $status pending, valid or expired
 * @property Carbon|null $valid_at
 * @property int|null $premium_grant_id
 * @property Carbon $created_at
 * @property-read User $inviter
 * @property-read User $invitee
 * @property-read PremiumGrant|null $premiumGrant
 */
class Referral extends Model
{
    public const PENDING = 'pending';

    public const VALID = 'valid';

    public const EXPIRED = 'expired';

    protected $fillable = ['inviter_id', 'invitee_id', 'status', 'valid_at', 'premium_grant_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['valid_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    /**
     * The Premium days the inviter got for this referral, if any.
     *
     * @return BelongsTo<PremiumGrant, $this>
     */
    public function premiumGrant(): BelongsTo
    {
        return $this->belongsTo(PremiumGrant::class);
    }
}
