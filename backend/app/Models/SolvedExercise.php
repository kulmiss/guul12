<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use App\Models\Concerns\RecordsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $uid
 * @property int $chapter_id
 * @property int $sort
 * @property string $problem
 * @property list<string> $steps
 * @property string $final_answer
 * @property QuestionStatus $status
 * @property string|null $source
 */
class SolvedExercise extends Model
{
    use RecordsContentChanges;

    protected $fillable = ['uid', 'chapter_id', 'sort', 'problem', 'steps', 'final_answer', 'status', 'source'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'sort' => 'integer',
            'status' => QuestionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function contentEntity(): string
    {
        return 'solved_exercise';
    }

    public function contentChapterId(): ?int
    {
        return $this->chapter_id;
    }
}
