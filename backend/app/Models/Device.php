<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $device_hash
 * @property string $platform
 * @property string|null $app_version
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property-read User $user
 */
class Device extends Model
{
    protected $fillable = ['user_id', 'device_hash', 'platform', 'app_version', 'first_seen_at', 'last_seen_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public static function hashInstallId(string $installId): string
    {
        return hash('sha256', 'guul12-install:'.strtolower(trim($installId)));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
