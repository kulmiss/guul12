<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Card;
use Laravel\Nova\Dashboard;

/** For content checkers (and the super admin): reports, questions close to publishing, weak spots. */
/*
 * Not limited with canSee(): Nova then crashes (error 500) when another role calls a card's address.
 * Instead the menu link shows only to content checkers, and every card checks the role itself, so another
 * role who types the address sees an empty page.
 */
class Content extends Dashboard
{
    public function name(): string
    {
        return 'Content';
    }

    /** @return array<int, Card> */
    public function cards(): array
    {
        return [
            DashboardCards::openQuestionReports(),
            DashboardCards::openNicknameReports(),
            DashboardCards::betaNearPublishing(),
            DashboardCards::questionsPublished(),
            DashboardCards::questionsBeta(),
            DashboardCards::questionsByStatus(),
            DashboardCards::mostReportedQuestions(),
            DashboardCards::hardestQuestions(),
            DashboardCards::weakestChapters(),
            DashboardCards::latestReports(),
        ];
    }

    public function uriKey(): string
    {
        return 'content';
    }
}
