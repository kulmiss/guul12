<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property bool $enabled
 * @property string|null $description
 */
class FeatureSwitch extends Model
{
    protected $fillable = ['key', 'enabled', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
