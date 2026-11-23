<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $question_id
 * @property int $question_version
 * @property string $body
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cost_micro_usd
 * @property int $helpful
 * @property int $not_helpful
 * @property bool $hidden
 */
class AiExplanation extends Model
{
    protected $fillable = ['question_id', 'question_version', 'body', 'model', 'input_tokens', 'output_tokens', 'cost_micro_usd'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_version' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_micro_usd' => 'integer',
            'helpful' => 'integer',
            'not_helpful' => 'integer',
            'hidden' => 'boolean',
        ];
    }
}
