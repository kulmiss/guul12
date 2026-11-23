<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Access;
use App\Http\Controllers\Controller;
use App\Models\Mock;
use App\Models\Passage;
use App\Models\Question;
use App\Models\User;
use App\Services\ContentPayload;
use App\Services\PremiumService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fixed mock exams.
 *
 * - /mocks: the list, with what each needs (free or Premium) and a hash that changes with its questions.
 * - /mocks/{id}: everything to sit the mock offline. A Premium mock is refused to free students.
 */
class MockController extends Controller
{
    public function __construct(
        private readonly ContentPayload $payload,
        private readonly PremiumService $premium,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isPremium = $this->premium->isPremium($user);

        $mocks = Mock::query()->openToStudents()
            ->with(['questions' => fn ($q) => $q->visibleToStudents()])
            ->orderBy('subject_id')->orderBy('grade')->orderBy('number')
            ->get();

        return response()->json([
            'mocks' => $mocks->map(fn (Mock $m): array => $this->header($m, $m->questions, $isPremium))->values()->all(),
        ]);
    }

    public function show(Request $request, Mock $mock): JsonResponse
    {
        abort_unless($mock->isOpenToStudents(), 404);
        /** @var User $user */
        $user = $request->user();
        $isPremium = $this->premium->isPremium($user);
        if ($mock->access === Access::Premium && ! $isPremium) {
            return response()->json(['code' => 'premium_required', 'message' => 'Premium'], 403);
        }

        /** @var Collection<int, Question> $questions */
        $questions = $mock->questions()->visibleToStudents()->with(['options', 'passage'])->get();
        /** @var \Illuminate\Support\Collection<int, Passage> $passages */
        $passages = $questions->pluck('passage')->filter()->unique('id')->values();

        return response()->json([
            'mock' => $this->header($mock, $questions, $isPremium),
            'passages' => $passages->map(fn (Passage $p): array => [
                'id' => $p->id,
                'title' => $p->title,
                'body' => $p->body,
            ])->all(),
            'questions' => $questions->map(fn (Question $q): array => $this->payload->question($q))->values()->all(),
        ]);
    }

    /**
     * @param  Collection<int, Question>  $questions  the mock's visible questions, in order
     * @return array<string, mixed>
     */
    private function header(Mock $mock, Collection $questions, bool $isPremium): array
    {
        return [
            'id' => $mock->id,
            'subject_id' => $mock->subject_id,
            'grade' => $mock->grade,
            'number' => $mock->number,
            'title' => $mock->title,
            'minutes' => $mock->minutes,
            'question_count' => $questions->count(),
            'access' => $mock->access->value,
            'locked' => $mock->access === Access::Premium && ! $isPremium,
            // Changes when a question is added, removed, reordered or edited: the app downloads it again.
            'content_hash' => sha1((string) json_encode($questions->map(fn (Question $q): array => [$q->id, $q->version])->values()->all())),
        ];
    }
}
