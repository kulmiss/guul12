<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use App\Models\Concerns\RecordsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $chapter_id
 * @property int $sort
 * @property string $term
 * @property string $definition
 * @property QuestionStatus $status
 */
class Flashcard extends Model
{
    use RecordsContentChanges;

    protected $fillable = ['chapter_id', 'sort', 'term', 'definition', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
        return 'flashcard';
    }

    public function contentChapterId(): ?int
    {
        return $this->chapter_id;
    }
}
