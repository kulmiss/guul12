<?php

namespace App\Models;

use App\Models\Concerns\RecordsContentChanges;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $subject_id
 * @property int $grade
 * @property string|null $stream
 * @property int $number
 * @property string $title
 * @property int $sort
 * @property bool $is_active
 * @property-read Subject $subject
 */
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, RecordsContentChanges;

    public const GRADES = [9, 10, 11, 12];

    protected $fillable = ['subject_id', 'grade', 'stream', 'number', 'title', 'sort', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'number' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return HasMany<Chapter, $this>
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    public function contentEntity(): string
    {
        return 'unit';
    }

    public function contentChapterId(): ?int
    {
        return null;
    }
}
