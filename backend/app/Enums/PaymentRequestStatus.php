<?php

namespace App\Enums;

enum PaymentRequestStatus: string
{
    // Noted by staff, not checked yet.
    case Waiting = 'waiting';
    // Checked: Premium was given.
    case Approved = 'approved';
    // Checked: no Premium (wrong reference, amount, …).
    case Rejected = 'rejected';
}
