<?php

namespace App\Nova\Lenses;

use App\Services\Quality\QualityGate;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\LensRequest;

/**
 * Beta (or fixed) questions the quality gate will publish at its next run: enough answers, enough
 * of them right, no open report. Uses the gate's own rule, so this list and the gate always agree.
 */
class ReadyToPublish extends QuestionLens
{
    public const SLUG = 'ready-to-publish';

    public $name = 'Beta ready to publish';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        $ready = app(QualityGate::class)->readyToPublish()->select('questions.id');

        return self::finish($request, self::base($query)->whereIn('questions.id', $ready), fn (Builder $q) => $q->orderByDesc('times_answered'));
    }
}
