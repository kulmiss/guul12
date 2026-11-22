<?php

namespace App\Models;

use App\Enums\PracticeMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $client_uuid
 * @property PracticeMode $mode
 * @property int|null $chapter_id
 * @property int|null $mock_id
 * @property Carbon $started_at
 * @property Carbon $finished_at
 * @property int $total
 * @property int $correct
 * @property int $too_fast
 * @property-read Collection<int, AttemptAnswer> $answers
 */
class Attempt extends Model
{
    protected $fillable = ['user_id', 'client_uuid', 'mode', 'chapter_id', 'mock_id', 'started_at', 'finished_at', 'total', 'correct', 'too_fast'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => PracticeMode::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'total' => 'integer',
            'correct' => 'integer',
            'too_fast' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }
}
