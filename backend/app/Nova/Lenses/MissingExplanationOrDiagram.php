<?php

namespace App\Nova\Lenses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\LensRequest;

/**
 * Questions to improve: an explanation shorter than 5 characters ("B."), or text that talks about a
 * diagram, figure or picture while no picture is attached.
 */
class MissingExplanationOrDiagram extends QuestionLens
{
    public const KEY = 'missing-explanation-or-diagram';

    public $name = 'Missing explanation or diagram';

    /** Words that mean the question needs a picture. */
    public const PICTURE_WORDS = ['diagram', 'figure', 'picture', 'shown below', 'graph below', 'the graph shows'];

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return self::finish($request, self::base($query)->where(function (Builder $q): void {
            $q->whereRaw('length(trim(explanation)) < 5')
                ->orWhere(function (Builder $noPicture): void {
                    $noPicture->whereNull('media_path')->where(function (Builder $words): void {
                        foreach (self::PICTURE_WORDS as $word) {
                            $words->orWhereRaw('lower(stem) like ?', ['%'.$word.'%']);
                        }
                    });
                });
        }), fn (Builder $q) => $q->orderBy('chapter_id'));
    }
}
