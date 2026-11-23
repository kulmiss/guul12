<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $key
 * @property int|null $subject_id
 * @property bool $is_random
 * @property list<int> $question_ids
 * @property int $creator_id
 * @property int|null $opponent_id
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property-read User $creator
 * @property-read User|null $opponent
 * @property-read Subject|null $subject
 */
class Battle extends Model
{
    protected $fillable = ['code', 'key', 'subject_id', 'is_random', 'question_ids', 'creator_id', 'opponent_id', 'expires_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_random' => 'boolean',
            'question_ids' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The players' answers to this battle (one attempt each).
     *
     * @return HasMany<Attempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function hasPlayer(User $user): bool
    {
        return $this->creator_id === $user->id || $this->opponent_id === $user->id;
    }
}
