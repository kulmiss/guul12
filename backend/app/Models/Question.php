<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\Concerns\RecordsContentChanges;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uid
 * @property string|null $content_hash
 * @property int $chapter_id
 * @property int|null $passage_id
 * @property QuestionType $type
 * @property string $stem
 * @property string|null $media_path
 * @property int $difficulty
 * @property QuestionStatus $status
 * @property array<string, mixed> $answer_key
 * @property string $explanation
 * @property string|null $source
 * @property list<string>|null $tags
 * @property int $version
 * @property int $times_answered
 * @property int $times_correct
 * @property int $reports_count
 * @property Carbon|null $published_at
 * @property-read Chapter $chapter
 * @property-read Passage|null $passage
 * @property-read Collection<int, QuestionOption> $options
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, RecordsContentChanges;

    /**
     * Fields whose change means students saw a different question, so the version goes up.
     */
    private const VERSIONED_FIELDS = ['type', 'stem', 'media_path', 'answer_key', 'explanation', 'passage_id'];

    protected $fillable = [
        'uid', 'chapter_id', 'passage_id', 'type', 'stem', 'media_path', 'difficulty', 'status',
        'answer_key', 'explanation', 'source', 'tags',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'status' => QuestionStatus::class,
            'answer_key' => 'array',
            'tags' => 'array',
            'difficulty' => 'integer',
            'version' => 'integer',
            'times_answered' => 'integer',
            'times_correct' => 'integer',
            'reports_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $question): void {
            if ($question->exists && $question->isDirty(self::VERSIONED_FIELDS)) {
                $question->version = $question->version + 1;
            }

            if ($question->status === QuestionStatus::Published && $question->published_at === null) {
                $question->published_at = Carbon::now();
            }
        });
    }

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * @return BelongsTo<Passage, $this>
     */
    public function passage(): BelongsTo
    {
        return $this->belongsTo(Passage::class);
    }

    /**
     * @return HasMany<QuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleToStudents(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), array_map(fn (QuestionStatus $s) => $s->value, QuestionStatus::visibleToStudents()));
    }

    /**
     * Called when a related row (option, passage) changes: records a sync change and,
     * for questions students can already see, bumps the version.
     */
    public function touchContent(): void
    {
        if ($this->status->isVisibleToStudents()) {
            $this->version = $this->version + 1;
        }

        $this->updated_at = $this->freshTimestamp();
        $this->save();
    }

    public function contentEntity(): string
    {
        return 'question';
    }

    public function contentChapterId(): ?int
    {
        return $this->chapter_id;
    }
}
