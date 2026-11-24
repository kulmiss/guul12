<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'question_ids' => $user->bookmarks()->orderBy('id')->pluck('question_id')->all(),
        ]);
    }

    public function store(Request $request, Question $question): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($question->status->isVisibleToStudents(), 404);

        Bookmark::query()->firstOrCreate(['user_id' => $user->id, 'question_id' => $question->id]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Question $question): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->bookmarks()->where('question_id', $question->id)->delete();

        return response()->json(['ok' => true]);
    }
}
