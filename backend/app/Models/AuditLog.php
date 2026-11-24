<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $data
 * @property string|null $ip
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'data', 'ip'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function write(?User $actor, string $action, ?Model $subject = null, array $data = []): self
    {
        return self::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject !== null ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'data' => $data === [] ? null : $data,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * A change, with the values before and after (only the keys that changed are kept).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $extra  e.g. the reason
     */
    public static function change(?User $actor, string $action, ?Model $subject, array $old, array $new, array $extra = []): self
    {
        $keys = array_values(array_filter(array_unique([...array_keys($old), ...array_keys($new)]),
            fn (string $key): bool => ($old[$key] ?? null) !== ($new[$key] ?? null)));
        $pick = fn (array $values): array => array_combine($keys, array_map(fn (string $k): mixed => $values[$k] ?? null, $keys));

        return self::write($actor, $action, $subject, ['old' => $pick($old), 'new' => $pick($new), ...$extra]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
