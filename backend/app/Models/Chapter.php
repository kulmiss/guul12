<?php

namespace App\Models;

use App\Enums\Access;
use App\Models\Concerns\RecordsContentChanges;
use Database\Factories\ChapterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $unit_id
 * @property int $number
 * @property string $title
 * @property string|null $textbook_url
 * @property string|null $video_youtube_id
 * @property Access $video_access
 * @property string|null $summary_pdf_path
 * @property int $sort
 * @property bool $is_active
 * @property-read Unit $unit
 * @property-read Summary|null $summary
 */
class Chapter extends Model
{
    /** @use HasFactory<ChapterFactory> */
    use HasFactory, RecordsContentChanges;

    protected $fillable = [
        'unit_id', 'number', 'title', 'textbook_url', 'video_youtube_id', 'video_access',
        'summary_pdf_path', 'sort', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
            'video_access' => Access::class,
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasOne<Summary, $this>
     */
    public function summary(): HasOne
    {
        return $this->hasOne(Summary::class);
    }

    /**
     * @return HasMany<SolvedExercise, $this>
     */
    public function solvedExercises(): HasMany
    {
        return $this->hasMany(SolvedExercise::class);
    }

    /**
     * @return HasMany<Flashcard, $this>
     */
    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class);
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function contentEntity(): string
    {
        return 'chapter';
    }

    public function contentChapterId(): ?int
    {
        return $this->id;
    }
}
