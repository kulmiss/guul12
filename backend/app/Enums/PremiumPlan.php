<?php

namespace App\Enums;

enum PremiumPlan: string
{
    case Term = 'term';
    case Year = 'year';
    case Referral = 'referral';

    // Days a staff member adds by hand (compensation, prize). Never paid for, never a payment request.
    case Extra = 'extra';
}
