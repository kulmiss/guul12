<?php

namespace App\Nova\Dashboards;

use App\Models\User;
use App\Nova\Cards\FindStudent;
use App\Nova\Metrics\AiSpend;
use App\Nova\Metrics\CachedPartition;
use App\Nova\Metrics\CachedTable;
use App\Nova\Metrics\CachedTrend;
use App\Nova\Metrics\CachedValue;
use App\Nova\Metrics\SystemHealth;
use Illuminate\Http\Request;
use Laravel\Nova\Card;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Metrics\MetricTableRow;

/**
 * Every dashboard card in one place, each with the roles that may see it. A card a role cannot see
 * is also refused when its address is called directly (Nova checks canSee for metric requests).
 * Numbers come from the cache that guul:stats refreshes every 10 minutes.
 */
final class DashboardCards
{
    public const EVERYONE = null;

    // ---- students (all staff) -------------------------------------------------------------------

    public static function newStudents(): Card
    {
        return new CachedValue('new-students', 'New students', 'new_students', ranged: true,
            help: 'Accounts created in the range. The arrow compares with the same length of time just before.');
    }

    public static function activeStudents(): Card
    {
        return new CachedValue('active-students', 'Active students', 'active_students', ranged: true, defaultRange: '7',
            help: 'Students who opened the app in the range (last visit). No comparison: only the newest visit is kept.');
    }

    public static function answersGiven(): Card
    {
        return new CachedValue('answers-given', 'Answers given', 'answers_given', ranged: true,
            help: 'Answers that reached the server: practice, exams, challenges and battles.');
    }

    public static function newStudentsPerDay(): Card
    {
        return new CachedTrend('new-students-per-day', 'New students per day', 'new_students_per_day');
    }

    public static function answeringStudentsPerDay(): Card
    {
        return new CachedTrend('answering-students-per-day', 'Students answering per day', 'answering_students_per_day',
            help: 'Students who answered at least one question that day. The total counts a student once per day.');
    }

    public static function answersPerDay(): Card
    {
        return new CachedTrend('answers-per-day', 'Questions answered per day', 'answers_per_day');
    }

    public static function studyMinutesPerDay(): Card
    {
        return new CachedTrend('study-minutes-per-day', 'Study minutes per day', 'study_minutes_per_day',
            help: 'Worked out from the time students spent on each answer (very long pauses are capped).', suffix: 'min');
    }

    /** @return list<Card> */
    public static function studentPartitions(): array
    {
        return [
            new CachedPartition('students-by-grade', 'Students by grade', 'students_by_grade'),
            new CachedPartition('students-by-stream', 'Students by stream', 'students_by_stream'),
            new CachedPartition('students-by-region', 'Students by region', 'students_by_region'),
            new CachedPartition('students-by-gender', 'Students by gender', 'students_by_gender',
                help: 'Gender is optional; "Not given" is everyone who skipped it.'),
        ];
    }

    public static function topStudentsThisWeek(): Card
    {
        return new CachedTable('top-students-week', 'Top 10 students this week', 'top_students_week',
            fn (array $r): MetricTableRow => MetricTableRow::make()
                ->icon('trophy')->iconClass('text-yellow-500')
                ->title('#'.$r['rank'].' '.$r['nickname'])->subtitle(number_format((int) $r['xp']).' XP'),
            empty: 'Nobody has points this week yet.',
            help: 'The weekly leaderboard. Only nicknames are shown, never student IDs.');
    }

    public static function battlesPlayed(): Card
    {
        return (new CachedValue('battles-played', 'Battles played', 'battles_played', ranged: true,
            help: 'Battles where a second student joined.'))->canSee(self::allowedTo('moderate'));
    }

    // ---- premium (super admin + payments helper) ------------------------------------------------

    public static function premiumActive(): Card
    {
        return (new CachedValue('premium-active', 'Premium students now', 'premium_active',
            help: 'Students with Premium today.'))->canSee(self::allowedTo('manage-premium'));
    }

    public static function premiumSold(): Card
    {
        return (new CachedValue('premium-sold', 'Premium sold', 'premium_sold', ranged: true,
            help: 'Paid Term or Year periods given (undone ones and referral rewards are not counted).'))->canSee(self::allowedTo('manage-premium'));
    }

    public static function premiumExpiring(): Card
    {
        return (new CachedValue('premium-expiring', 'Premium ending in 7 days', 'premium_expiring_7',
            help: 'Students whose Premium ends in the next 7 days and who have nothing after it.'))->canSee(self::allowedTo('manage-premium'));
    }

    public static function paymentsWaiting(): Card
    {
        return (new CachedValue('payments-waiting', 'Payments waiting', 'payments_waiting',
            help: 'Payment requests nobody has checked yet. Open Payment requests to approve or reject them.'))->canSee(self::allowedTo('manage-premium'));
    }

    public static function premiumSoldPerWeek(): Card
    {
        return (new CachedTrend('premium-sold-per-week', 'Premium sold per week', 'premium_sold_per_week'))->canSee(self::allowedTo('manage-premium'));
    }

    public static function premiumVsFree(): Card
    {
        return (new CachedPartition('premium-vs-free', 'Premium vs free', 'premium_vs_free'))->canSee(self::allowedTo('manage-premium'));
    }

    public static function latestPayments(): Card
    {
        return (new CachedTable('latest-payments', 'Latest payment requests', 'latest_payments',
            fn (array $r): MetricTableRow => MetricTableRow::make()
                ->icon(match ($r['status']) {
                    'approved' => 'check-circle', 'rejected' => 'x-circle', default => 'clock'
                })
                ->iconClass(match ($r['status']) {
                    'approved' => 'text-green-500', 'rejected' => 'text-red-500', default => 'text-yellow-500'
                })
                ->title($r['student_id'].' · '.ucfirst((string) $r['plan']))
                ->subtitle('Ref '.$r['payment_ref'].' · '.ucfirst((string) $r['status']))
                ->actions(fn (): array => [MenuItem::link('Open', '/resources/payment-requests/'.$r['id'])]),
            empty: 'No payment requests yet.'))->canSee(self::allowedTo('manage-premium'));
    }

    public static function referralsRewarded(): Card
    {
        return (new CachedValue('referrals-rewarded', 'Referrals rewarded', 'referrals_rewarded', ranged: true,
            help: 'Invited friends who studied enough, so the inviter got the reward.'))->canSee(self::allowedTo('manage-premium'));
    }

    public static function findStudent(): Card
    {
        return (new FindStudent)->canSee(self::allowedTo('manage-premium'));
    }

    // ---- content (super admin + content checker) ------------------------------------------------

    public static function openQuestionReports(): Card
    {
        return (new CachedValue('open-reports', 'Open question reports', 'open_question_reports',
            help: 'Reports from students that nobody has checked yet.'))->canSee(self::allowedTo('manage-content'));
    }

    public static function openNicknameReports(): Card
    {
        return (new CachedValue('open-nickname-reports', 'Open nickname reports', 'open_nickname_reports'))->canSee(self::allowedTo('moderate'));
    }

    public static function questionsPublished(): Card
    {
        return (new CachedValue('questions-published', 'Published questions', 'questions_published'))->canSee(self::allowedTo('manage-content'));
    }

    public static function questionsBeta(): Card
    {
        return (new CachedValue('questions-beta', 'Beta questions', 'questions_beta',
            help: 'Checked twice, waiting for enough students to answer them without reports.'))->canSee(self::allowedTo('manage-content'));
    }

    public static function betaNearPublishing(): Card
    {
        return (new CachedValue('beta-near-publishing', 'Beta near publishing', 'beta_near_publishing',
            help: 'Beta questions with no reports and at least half of the answers they need to be published.'))->canSee(self::allowedTo('manage-content'));
    }

    public static function questionsByStatus(): Card
    {
        return (new CachedPartition('questions-by-status', 'Questions by status', 'questions_by_status'))->canSee(self::allowedTo('manage-content'));
    }

    public static function hardestQuestions(): Card
    {
        return (new CachedTable('hardest-questions', 'Hardest 10 questions', 'hardest_questions',
            fn (array $r): MetricTableRow => self::questionRow($r, $r['percent'].'% right · '.$r['answered'].' answers'),
            empty: 'No question has 20 answers yet.',
            help: 'Lowest share of right answers (at least 20 answers). Very low can mean a wrong key: check it.'))->canSee(self::allowedTo('manage-content'));
    }

    public static function weakestChapters(): Card
    {
        return (new CachedTable('weakest-chapters', 'Weakest 10 chapters', 'weakest_chapters',
            fn (array $r): MetricTableRow => MetricTableRow::make()
                ->icon('book-open')->iconClass('text-orange-500')
                ->title((string) $r['title'])->subtitle($r['percent'].'% right · '.$r['answered'].' answers')
                ->actions(fn (): array => [MenuItem::link('Open', '/resources/chapters/'.$r['id'])]),
            empty: 'No chapter has 50 answers yet.'))->canSee(self::allowedTo('manage-content'));
    }

    public static function mostReportedQuestions(): Card
    {
        return (new CachedTable('most-reported-questions', 'Most reported questions', 'most_reported_questions',
            fn (array $r): MetricTableRow => self::questionRow($r, $r['reports'].' report(s)'),
            empty: 'No reports yet.'))->canSee(self::allowedTo('manage-content'));
    }

    public static function latestReports(): Card
    {
        return (new CachedTable('latest-reports', 'Latest reports', 'latest_reports',
            fn (array $r): MetricTableRow => MetricTableRow::make()
                ->icon($r['type'] === 'question' ? 'flag' : 'user')->iconClass('text-yellow-500')
                ->title((string) $r['text'])->subtitle($r['type'] === 'question' ? 'Question report' : 'Nickname report')
                ->actions(fn (): array => [MenuItem::link('Open', ($r['type'] === 'question' ? '/resources/question-reports/' : '/resources/nickname-reports/').$r['id'])]),
            empty: 'No reports yet.'))->canSee(self::allowedTo('moderate'));
    }

    // ---- super admin --------------------------------------------------------------------------------

    public static function aiSpend(): Card
    {
        return (new AiSpend)->canSee(self::allowedTo('manage-settings'));
    }

    public static function systemHealth(): Card
    {
        return (new SystemHealth)->canSee(self::allowedTo('manage-settings'));
    }

    // ---- helpers ------------------------------------------------------------------------------------

    /** @param  array<string, mixed>  $r */
    private static function questionRow(array $r, string $subtitle): MetricTableRow
    {
        return MetricTableRow::make()
            ->icon('question-mark-circle')->iconClass('text-sky-500')
            ->title((string) $r['text'])->subtitle($subtitle)
            ->actions(fn (): array => [MenuItem::link('Open', '/resources/questions/'.$r['id'])]);
    }

    /** @return \Closure(Request): bool */
    public static function allowedTo(string $ability): \Closure
    {
        return fn (Request $request): bool => $request->user() instanceof User && $request->user()->can($ability);
    }
}
