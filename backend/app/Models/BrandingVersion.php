<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One saved branding (logo, colours, fonts, roundness). The newest row is the one in use (DECISIONS #35).
 *
 * @property int $id
 * @property array<string, mixed> $data
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read User|null $creator
 */
class BrandingVersion extends Model
{
    protected $fillable = ['data', 'note', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
