<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable setting (exam date, free limits, prices for the landing page, …).
 * Read through App\Services\Settings, which supplies defaults.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
