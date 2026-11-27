<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Services\NicknameModeration;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $student_id
 * @property UserRole $role
 * @property string|null $name
 * @property string|null $email
 * @property string|null $password
 * @property string|null $google_sub
 * @property int|null $grade
 * @property string|null $stream
 * @property bool $is_retaker
 * @property string|null $region
 * @property string|null $city
 * @property Gender|null $gender
 * @property int|null $target_score
 * @property string|null $reminder_time
 * @property string|null $referral_code
 * @property string|null $nickname
 * @property Carbon|null $leaderboard_blocked_at
 * @property string|null $avatar_style
 * @property string|null $avatar_seed
 * @property Carbon|null $onboarded_at
 * @property Carbon|null $last_active_at
 * @property Carbon $created_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Deleting a student (app or Nova) also removes what the database cannot remove by itself:
     * sign-in tokens and staff log lines about the student. Only the Premium accounting lines
     * stay (privacy page, "What we keep").
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            $user->tokens()->delete();
            $reports = NicknameReport::query()
                ->where(fn ($q) => $q->where('reporter_id', $user->id)->orWhere('reported_user_id', $user->id))
                ->pluck('id');
            AuditLog::query()->where('subject_type', 'User')->where('subject_id', $user->id)->delete();
            AuditLog::query()->where('subject_type', 'NicknameReport')->whereIn('subject_id', $reports)->delete();
        });
        static::deleted(function (): void {
            // A deleted nickname leaves the cached leaderboard at once.
            NicknameModeration::refreshLeaderboard();
        });
    }

    /**
     * Only profile fields are mass assignable; role, student_id and google_sub are set explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'grade',
        'stream',
        'is_retaker',
        'region',
        'city',
        'gender',
        'target_score',
        'reminder_time',
        'nickname',
        'avatar_style',
        'avatar_seed',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_sub',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'gender' => Gender::class,
            'is_retaker' => 'boolean',
            'email_verified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'last_active_at' => 'datetime',
            'leaderboard_blocked_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Grants that are not undone and not over yet (for lists that show Premium for many students).
     *
     * @return HasMany<PremiumGrant, $this>
     */
    public function currentPremiumGrants(): HasMany
    {
        return $this->hasMany(PremiumGrant::class)->whereNull('revoked_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * @return HasMany<QuestionReport, $this>
     */
    public function questionReports(): HasMany
    {
        return $this->hasMany(QuestionReport::class);
    }

    /**
     * Reports other students made about this student's nickname.
     *
     * @return HasMany<NicknameReport, $this>
     */
    public function nicknameReportsAbout(): HasMany
    {
        return $this->hasMany(NicknameReport::class, 'reported_user_id');
    }

    /**
     * Friends this student invited.
     *
     * @return HasMany<Referral, $this>
     */
    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'inviter_id');
    }

    /**
     * Activity log rows about this account.
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'subject_id')->where('subject_type', 'User');
    }

    /**
     * @return HasMany<PaymentRequest, $this>
     */
    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * @return HasMany<PremiumGrant, $this>
     */
    public function premiumGrants(): HasMany
    {
        return $this->hasMany(PremiumGrant::class);
    }

    /**
     * @return HasMany<Attempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /**
     * @return HasMany<Bookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
