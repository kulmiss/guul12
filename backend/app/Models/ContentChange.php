<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $entity
 * @property int $entity_id
 * @property int|null $chapter_id
 * @property string $action
 * @property Carbon $created_at
 */
class ContentChange extends Model
{
    public const ACTION_UPSERT = 'upsert';

    public const ACTION_DELETE = 'delete';

    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'chapter_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public static function record(string $entity, int $entityId, ?int $chapterId, string $action): self
    {
        return self::query()->create([
            'entity' => $entity,
            'entity_id' => $entityId,
            'chapter_id' => $chapterId,
            'action' => $action,
        ]);
    }

    /**
     * The newest cursor value, or 0 when nothing has changed yet.
     */
    public static function latestCursor(): int
    {
        return (int) self::query()->max('id');
    }
}
