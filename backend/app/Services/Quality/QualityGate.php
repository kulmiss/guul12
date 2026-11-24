<?php

namespace App\Services\Quality;

use App\Contracts\StaffNotifier;
use App\Enums\QuestionStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Student reports and the Beta → Published step of the quality gate.
 */
final class QualityGate
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Saves a report (one per student per question version). Enough open reports send a
     * visible question to review ("reported"); it stays visible until a checker decides.
     *
     * @return array{0: QuestionReport, 1: bool} the report and whether it is new
     */
    public function report(User $user, Question $question, ReportReason $reason, ?string $note): array
    {
        $existing = $this->existing($user, $question);
        if ($existing !== null) {
            return [$existing, false];
        }

        try {
            $report = DB::transaction(function () use ($user, $question, $reason, $note): QuestionReport {
                $report = QuestionReport::query()->create([
                    'question_id' => $question->id,
                    'user_id' => $user->id,
                    'question_version' => $question->version,
                    'reason' => $reason,
                    'note' => $note,
                    'status' => ReportStatus::Open,
                ]);
                Question::query()->whereKey($question->id)->increment('reports_count');

                return $report;
            });
        } catch (UniqueConstraintViolationException) {
            // Two taps at once: the other request saved it.
            return [$this->existing($user, $question) ?? throw new \LogicException('report vanished'), false];
        }

        // The first open report on this question rings the content staff (not every student's report).
        if (QuestionReport::query()->where('question_id', $question->id)->where('status', ReportStatus::Open->value)->count() === 1) {
            app(StaffNotifier::class)->notifyAll('manage-content',
                "New report on question #{$question->id}: ".str_replace('_', ' ', $reason->value).'.', 'warning', "/resources/questions/{$question->id}");
        }

        $open = QuestionReport::query()
            ->where('question_id', $question->id)
            ->where('question_version', $question->version)
            ->where('status', ReportStatus::Open->value)
            ->count();

        if ($open >= $this->settings->int('report_threshold')
            && in_array($question->status, [QuestionStatus::Beta, QuestionStatus::Published, QuestionStatus::Fixed], true)) {
            $question->refresh();
            $question->status = QuestionStatus::Reported;
            $question->save();
            AuditLog::write(null, 'question.reported', $question, ['open_reports' => $open]);
        }

        return [$report, true];
    }

    /**
     * Daily: Beta questions answered often enough with no open report become Published.
     * Questions almost nobody gets right are sent to review (the key may be wrong).
     *
     * @return array{published: int, sent_to_review: int}
     */
    public function promote(): array
    {
        $minPercent = $this->settings->int('quality_min_correct_percent');
        $published = 0;
        $review = 0;

        $this->candidates()
            ->orderBy('id')
            ->chunkById(500, function ($questions) use ($minPercent, &$published, &$review): void {
                foreach ($questions as $question) {
                    /** @var Question $question */
                    $percent = 100 * $question->times_correct / max(1, $question->times_answered);
                    if ($percent < $minPercent) {
                        $question->status = QuestionStatus::Reported;
                        $question->save();
                        AuditLog::write(null, 'question.low_correct_rate', $question, ['percent' => round($percent, 1)]);
                        $review++;

                        continue;
                    }
                    $question->status = QuestionStatus::Published;
                    $question->save();
                    $published++;
                }
            });

        return ['published' => $published, 'sent_to_review' => $review];
    }

    /**
     * Beta or fixed questions with enough answers and no open report: promote() decides each one.
     *
     * @return Builder<Question>
     */
    public function candidates(): Builder
    {
        return Question::query()
            ->whereIn('status', [QuestionStatus::Beta->value, QuestionStatus::Fixed->value])
            ->where('times_answered', '>=', max(1, $this->settings->int('beta_min_answers')))
            ->whereDoesntHave('reports', fn (Builder $q) => $q->where('status', ReportStatus::Open->value));
    }

    /**
     * The candidates promote() will publish (enough of the answers are right). Shown as a Nova lens.
     *
     * @return Builder<Question>
     */
    public function readyToPublish(): Builder
    {
        return $this->candidates()
            ->whereRaw('times_correct * 100 >= ? * times_answered', [$this->settings->int('quality_min_correct_percent')]);
    }

    /**
     * A checker looked at the open reports of a question.
     * accepted = the question is wrong (fix it in the content files; the import marks it fixed);
     * rejected = the question is right; it goes back to Published (or Beta if still new).
     */
    public function resolve(Question $question, bool $accepted, User $by): int
    {
        $count = QuestionReport::query()
            ->where('question_id', $question->id)
            ->where('status', ReportStatus::Open->value)
            ->update([
                'status' => $accepted ? ReportStatus::Accepted->value : ReportStatus::Rejected->value,
                'resolved_by' => $by->id,
                'resolved_at' => now(),
            ]);

        if (! $accepted && $question->status === QuestionStatus::Reported) {
            $question->status = $question->published_at !== null ? QuestionStatus::Published : QuestionStatus::Beta;
            $question->save();
        }
        AuditLog::write($by, $accepted ? 'question.reports_accepted' : 'question.reports_rejected', $question, ['reports' => $count]);

        return $count;
    }

    private function existing(User $user, Question $question): ?QuestionReport
    {
        return QuestionReport::query()
            ->where('question_id', $question->id)
            ->where('user_id', $user->id)
            ->where('question_version', $question->version)
            ->first();
    }
}
