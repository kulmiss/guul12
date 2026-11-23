<?php

namespace App\Enums;

enum NicknameReportStatus: string
{
    case Open = 'open';
    // Staff removed the nickname.
    case Reset = 'reset';
    // Checked: the nickname was fine.
    case Dismissed = 'dismissed';
}
