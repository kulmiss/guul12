<?php

namespace App\Models;

use App\Models\Concerns\RecordsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The solved review exercise of one textbook unit.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $book
 * @property string|null $pages
 * @property string|null $intro
 * @property list<array<string, mixed>> $items
 * @property int $question_count
 * @property-read Unit $unit
 */
class TextbookReview extends Model
{
    use RecordsContentChanges;

    protected $fillable = ['unit_id', 'book', 'pages', 'intro', 'items', 'question_count'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items' => 'array',
            'question_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function contentEntity(): string
    {
        return 'textbook_review';
    }

    public function contentChapterId(): ?int
    {
        return null;
    }
}
