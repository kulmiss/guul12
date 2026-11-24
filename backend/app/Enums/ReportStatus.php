<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    // A content checker agreed and fixed the question.
    case Accepted = 'accepted';
    // Checked: the question was right.
    case Rejected = 'rejected';
}
