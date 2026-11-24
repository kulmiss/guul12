<?php

namespace App\Models;

use App\Enums\Access;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A fixed mock exam: a set list of bank questions in a set order.
 *
 * @property int $id
 * @property string $uid
 * @property int $subject_id
 * @property int $grade
 * @property int $number
 * @property string $title
 * @property int $minutes
 * @property Access $access
 * @property bool $is_active
 * @property Carbon|null $unpublished_at
 * @property-read Subject $subject
 */
class Mock extends Model
{
    protected $fillable = ['uid', 'subject_id', 'grade', 'number', 'title', 'minutes', 'access', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'number' => 'integer',
            'minutes' => 'integer',
            'is_active' => 'boolean',
            'access' => Access::class,
            'unpublished_at' => 'datetime',
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
     * Mocks students can open: in the content files (is_active) and not unpublished by staff.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpenToStudents(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('unpublished_at');
    }

    public function isOpenToStudents(): bool
    {
        return $this->is_active && $this->unpublished_at === null;
    }

    /**
     * Questions in exam order.
     *
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'mock_questions')->withPivot('sort')->orderByPivot('sort');
    }
}
