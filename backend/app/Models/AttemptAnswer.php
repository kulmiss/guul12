<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $attempt_id
 * @property int $question_id
 * @property int $question_version
 * @property mixed $response
 * @property bool $is_correct
 * @property int $time_ms
 * @property bool $too_fast
 */
class AttemptAnswer extends Model
{
    protected $fillable = ['attempt_id', 'question_id', 'question_version', 'response', 'is_correct', 'time_ms', 'too_fast'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response' => 'json',
            'is_correct' => 'boolean',
            'too_fast' => 'boolean',
            'question_version' => 'integer',
            'time_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Attempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
