<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Card;
use Laravel\Nova\Dashboards\Main as Dashboard;

/**
 * The staff home page: an overview. Each role sees only the cards for its job (see DashboardCards).
 * The Payments and Content dashboards hold the full set for those jobs.
 */
class Main extends Dashboard
{
    public function name(): string
    {
        return 'Overview';
    }

    /** @return array<int, Card> */
    public function cards(): array
    {
        return [
            DashboardCards::newStudents(),
            DashboardCards::activeStudents(),
            DashboardCards::answersGiven(),
            DashboardCards::premiumActive(),
            DashboardCards::paymentsWaiting(),
            DashboardCards::openQuestionReports(),
            DashboardCards::openNicknameReports(),
            DashboardCards::battlesPlayed(),
            DashboardCards::aiSpend(),
            DashboardCards::systemHealth(),
            DashboardCards::newStudentsPerDay(),
            DashboardCards::answeringStudentsPerDay(),
            DashboardCards::answersPerDay(),
            DashboardCards::studyMinutesPerDay(),
            ...DashboardCards::studentPartitions(),
            DashboardCards::topStudentsThisWeek(),
        ];
    }
}
