<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Presenters\MePresenter;
use App\Models\Subject;
use App\Models\User;
use App\Services\NicknameFilter;
use App\Services\NicknameModeration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    /**
     * Avatar styles bundled offline in the app (all CC0, see docs/licences.md).
     */
    public const AVATAR_STYLES = ['notionists-neutral', 'lorelei-neutral', 'thumbs'];

    public function __construct(private readonly MePresenter $me) {}

    /**
     * DELETE /v1/me?confirm=delete-my-account — deletes the student's account and all their data
     * (profile, devices, answers, results, bookmarks, reports, battles, invites, Premium grants…;
     * every table cascades on the user). Login tokens are removed too. Staff accounts are refused.
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($request->query('confirm') !== 'delete-my-account') {
            return response()->json(['code' => 'confirm_required', 'message' => 'Confirm the deletion.'], 422);
        }
        if ($user->isStaff()) {
            return response()->json(['code' => 'staff_account', 'message' => 'Staff accounts are removed in Nova.'], 403);
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json(['deleted' => true]);
    }

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['user' => $this->me->present($user)]);
    }

    public function update(Request $request, NicknameFilter $nicknames, NicknameModeration $moderation): JsonResponse
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

            // Rude words, or a nickname staff removed before (in any spelling).
            if (! $nicknames->isAllowed($data['nickname']) || $moderation->wasRemoved($data['nickname'])) {
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
