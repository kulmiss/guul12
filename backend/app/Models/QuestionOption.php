<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $question_id
 * @property string $key
 * @property string|null $side
 * @property string $body
 * @property string|null $media_path
 * @property int $sort
 * @property-read Question $question
 */
class QuestionOption extends Model
{
    public const SIDE_LEFT = 'left';

    public const SIDE_RIGHT = 'right';

    protected $fillable = ['question_id', 'key', 'side', 'body', 'media_path', 'sort'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    protected static function booted(): void
    {
        $touchParent = function (self $option): void {
            $question = Question::query()->find($option->question_id);
            $question?->touchContent();
        };

        static::saved($touchParent);
        static::deleted($touchParent);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
