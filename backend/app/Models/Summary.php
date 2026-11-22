<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use App\Models\Concerns\RecordsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $chapter_id
 * @property list<string> $key_points
 * @property list<array{label: string, latex: string}> $formulas
 * @property string|null $body
 * @property QuestionStatus $status
 * @property string|null $source
 */
class Summary extends Model
{
    use RecordsContentChanges;

    protected $fillable = ['chapter_id', 'key_points', 'formulas', 'body', 'status', 'source'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key_points' => 'array',
            'formulas' => 'array',
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
        return 'summary';
    }

    public function contentChapterId(): ?int
    {
        return $this->chapter_id;
    }
}
