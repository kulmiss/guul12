<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property string $key
 * @property list<int> $question_ids
 */
class DailyChallenge extends Model
{
    protected $fillable = ['date', 'key', 'question_ids'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'question_ids' => 'array',
        ];
    }
}
