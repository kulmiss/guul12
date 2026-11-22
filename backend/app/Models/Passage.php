<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reading passage shared by several passage questions.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property string|null $source
 */
class Passage extends Model
{
    protected $fillable = ['title', 'body', 'source'];

    protected static function booted(): void
    {
        // A passage edit changes every question that uses it.
        static::saved(function (self $passage): void {
            $passage->questions()->each(fn (Question $q) => $q->touchContent());
        });
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
