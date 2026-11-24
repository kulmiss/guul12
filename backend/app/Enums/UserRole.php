<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case SuperAdmin = 'super_admin';
    case PaymentsHelper = 'payments_helper';
    case ContentChecker = 'content_checker';

    public function isStaff(): bool
    {
        return $this !== self::Student;
    }
}
