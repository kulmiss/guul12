<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
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
 * @property string|null $avatar_style
 * @property string|null $avatar_seed
 * @property Carbon|null $onboarded_at
 * @property Carbon|null $last_active_at
 * @property Carbon $created_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'password' => 'hashed',
        ];
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
