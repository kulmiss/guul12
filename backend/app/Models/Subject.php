<?php

namespace App\Models;

use App\Models\Concerns\RecordsContentChanges;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $stream
 * @property bool $is_exam_subject
 * @property string|null $icon
 * @property int $sort
 * @property bool $is_active
 */
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory, RecordsContentChanges;

    public const STREAM_NATURAL = 'natural';

    public const STREAM_SOCIAL = 'social';

    public const STREAMS = [self::STREAM_NATURAL, self::STREAM_SOCIAL];

    protected $fillable = ['code', 'name', 'stream', 'is_exam_subject', 'icon', 'sort', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_exam_subject' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function contentEntity(): string
    {
        return 'subject';
    }

    public function contentChapterId(): ?int
    {
        return null;
    }
}
