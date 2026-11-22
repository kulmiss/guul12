<?php

namespace App\Models\Concerns;

use App\Models\ContentChange;

/**
 * Writes a row to content_changes whenever a synced model is saved or deleted,
 * so the app can download only what changed since its last sync.
 */
trait RecordsContentChanges
{
    public static function bootRecordsContentChanges(): void
    {
        static::saved(function (self $model): void {
            ContentChange::record($model->contentEntity(), (int) $model->getKey(), $model->contentChapterId(), ContentChange::ACTION_UPSERT);
        });

        static::deleted(function (self $model): void {
            ContentChange::record($model->contentEntity(), (int) $model->getKey(), $model->contentChapterId(), ContentChange::ACTION_DELETE);
        });
    }

    /**
     * Short entity name used by the sync API, e.g. "question".
     */
    abstract public function contentEntity(): string;

    /**
     * Chapter this item belongs to, or null for catalog-level items.
     */
    abstract public function contentChapterId(): ?int;
}
