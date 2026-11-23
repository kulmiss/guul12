<?php

namespace App\Services\PaymentRequests;

/**
 * A payment request that cannot be created or decided. $reason is a short code for tests and logs;
 * the message is easy English for staff.
 */
class PaymentRequestProblem extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
