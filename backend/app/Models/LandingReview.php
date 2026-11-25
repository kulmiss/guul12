<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A real student review shown on the website (added by staff in Nova).
 *
 * @property int $id
 * @property string $name
 * @property int $grade
 * @property string|null $city
 * @property string $quote
 * @property int $rating
 * @property bool $permission_confirmed
 * @property bool $is_published
 * @property int $sort
 */
class LandingReview extends Model
{
    /** At most this many reviews are shown on the home page. */
    public const SHOWN = 6;

    protected $fillable = ['name', 'grade', 'city', 'quote', 'rating', 'permission_confirmed', 'is_published', 'sort'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'rating' => 'integer',
            'permission_confirmed' => 'boolean',
            'is_published' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * Reviews the website may show: switched on, with the student's permission and a valid rating.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeShown(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where('permission_confirmed', true)
            ->whereBetween('rating', [1, 5])
            ->orderBy('sort')
            ->orderBy('id')
            ->limit(self::SHOWN);
    }
}
