<?php

namespace App\Enums;

enum NicknameReportReason: string
{
    case Rude = 'rude';
    case Impersonation = 'impersonation';
    case Other = 'other';
}
