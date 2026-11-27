<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\User;
use App\Services\Quality\QualityGate;
use App\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    /** Reports one student may send per day (stops spam, plenty for honest use). */
    public const DAILY_LIMIT = 30;

    public function store(Request $request, Question $question, QualityGate $gate): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($question->status->isVisibleToStudents(), 404);

        $data = $request->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $today = QuestionReport::query()->where('user_id', $user->id)->where('created_at', '>=', LocalTime::startOfToday())->count();
        if ($today >= self::DAILY_LIMIT) {
            return response()->json(['code' => 'report_limit', 'message' => 'Thanks! You have sent many reports today. Please try again tomorrow.'], 429);
        }

        $note = isset($data['note']) && is_string($data['note']) ? trim($data['note']) : null;
        [$report, $created] = $gate->report($user, $question, ReportReason::from((string) $data['reason']), $note === '' ? null : $note);

        return response()->json([
            'report' => ['id' => $report->id, 'question_id' => $report->question_id, 'reason' => $report->reason->value],
        ], $created ? 201 : 200);
    }
}
