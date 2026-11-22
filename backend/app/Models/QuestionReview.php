<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One independent second solve of a question version (quality gate).
 *
 * @property int $id
 * @property int $question_id
 * @property int $question_version
 * @property int|null $reviewer_id
 * @property string $reviewer_name
 * @property mixed $response
 * @property bool $matches_key
 * @property string|null $note
 */
class QuestionReview extends Model
{
    protected $fillable = ['question_id', 'question_version', 'reviewer_id', 'reviewer_name', 'response', 'matches_key', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response' => 'json',
            'matches_key' => 'boolean',
            'question_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
