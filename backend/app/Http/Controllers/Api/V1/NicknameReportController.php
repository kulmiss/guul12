<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NicknameReportReason;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NicknameModeration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NicknameReportController extends Controller
{
    /**
     * POST /v1/nickname-reports — "Report nickname" from a leaderboard or battle row.
     * The row's private reference names the student; their current nickname is saved with the report.
     */
    public function store(Request $request, NicknameModeration $moderation): JsonResponse
    {
        /** @var User $me */
        $me = $request->user();

        $data = $request->validate([
            'ref' => ['required', 'string', 'max:1024'],
            'reason' => ['required', Rule::enum(NicknameReportReason::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $target = $moderation->userFromRef((string) $data['ref']);
        if ($target === null || $target->role->isStaff()) {
            return response()->json(['code' => 'unknown_student', 'message' => 'This student could not be found.'], 422);
        }
        if ($target->id === $me->id) {
            return response()->json(['code' => 'own_nickname', 'message' => 'You cannot report your own nickname.'], 422);
        }
        if ($target->nickname === null) {
            return response()->json(['code' => 'no_nickname', 'message' => 'This student has no nickname now.'], 422);
        }
        if ($moderation->reportsToday($me) >= NicknameModeration::DAILY_LIMIT) {
            return response()->json(['code' => 'report_limit', 'message' => 'Thanks! You have sent many reports today. Please try again tomorrow.'], 429);
        }

        $note = isset($data['note']) && is_string($data['note']) ? trim($data['note']) : null;
        [$report, $created] = $moderation->report($me, $target, NicknameReportReason::from((string) $data['reason']), $note === '' ? null : $note);

        return response()->json([
            'report' => ['id' => $report->id, 'reason' => $report->reason->value],
        ], $created ? 201 : 200);
    }
}
