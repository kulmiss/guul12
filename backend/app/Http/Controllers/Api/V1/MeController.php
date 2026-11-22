<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Presenters\MePresenter;
use App\Models\Subject;
use App\Models\User;
use App\Services\NicknameFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    /**
     * Avatar styles bundled offline in the app (all CC0, see docs/licences.md).
     */
    public const AVATAR_STYLES = ['notionists-neutral', 'lorelei-neutral', 'thumbs'];

    public function __construct(private readonly MePresenter $me) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['user' => $this->me->present($user)]);
    }

    public function update(Request $request, NicknameFilter $nicknames): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->refresh();

        $data = $request->validate([
            'grade' => ['sometimes', 'integer', 'between:9,12'],
            'stream' => ['sometimes', 'nullable', Rule::in(Subject::STREAMS)],
            'is_retaker' => ['sometimes', 'boolean'],
            'region' => ['sometimes', 'nullable', 'string', 'max:64'],
            'city' => ['sometimes', 'nullable', 'string', 'max:64'],
            'gender' => ['sometimes', 'nullable', Rule::enum(Gender::class)],
            'target_score' => ['sometimes', 'nullable', 'integer', 'between:0,600'],
            'reminder_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'nickname' => ['sometimes', 'nullable', 'string', 'min:3', 'max:24', 'regex:/^[\pL\pN _.-]+$/u'],
            'avatar_style' => ['sometimes', 'nullable', Rule::in(self::AVATAR_STYLES)],
            'avatar_seed' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'onboarded' => ['sometimes', 'accepted'],
        ]);

        if (isset($data['nickname']) && is_string($data['nickname'])) {
            $data['nickname'] = trim((string) preg_replace('/\s+/u', ' ', $data['nickname']));

            if (! $nicknames->isAllowed($data['nickname'])) {
                return response()->json([
                    'message' => 'Please choose another nickname.',
                    'errors' => ['nickname' => ['Please choose another nickname.']],
                ], 422);
            }
        }

        $onboarded = (bool) ($data['onboarded'] ?? false);
        unset($data['onboarded']);

        $user->fill($data);

        if ($onboarded && $user->onboarded_at === null) {
            $user->onboarded_at = Carbon::now();
        }

        $user->last_active_at = Carbon::now();
        $user->save();

        return response()->json(['user' => $this->me->present($user->refresh())]);
    }
}
