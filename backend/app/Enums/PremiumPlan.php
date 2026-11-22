<?php

namespace App\Enums;

enum PremiumPlan: string
{
    case Term = 'term';
    case Year = 'year';
    case Referral = 'referral';
}
