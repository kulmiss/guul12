<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Card;
use Laravel\Nova\Dashboard;

/** For payments helpers (and the super admin): what is waiting, what ends soon, what was sold. */
/*
 * Not limited with canSee(): Nova then crashes (error 500) when another role calls a card's address.
 * Instead the menu link shows only to payments helpers, and every card checks the role itself, so another
 * role who types the address sees an empty page.
 */
class Payments extends Dashboard
{
    public function name(): string
    {
        return 'Payments';
    }

    /** @return array<int, Card> */
    public function cards(): array
    {
        return [
            DashboardCards::paymentsWaiting(),
            DashboardCards::premiumExpiring(),
            DashboardCards::findStudent(),
            DashboardCards::premiumSold(),
            DashboardCards::premiumActive(),
            DashboardCards::referralsRewarded(),
            DashboardCards::premiumSoldPerWeek(),
            DashboardCards::premiumVsFree(),
            DashboardCards::latestPayments(),
        ];
    }

    public function uriKey(): string
    {
        return 'payments';
    }
}
