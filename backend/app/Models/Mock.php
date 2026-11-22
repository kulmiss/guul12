<?php

namespace App\Models;

use App\Enums\Access;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
     * Questions in exam order.
     *
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'mock_questions')->withPivot('sort')->orderByPivot('sort');
    }
}
