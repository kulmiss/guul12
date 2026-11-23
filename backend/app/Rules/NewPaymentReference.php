<?php

namespace App\Rules;

use App\Services\PaymentRequests;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A payment reference not used before by a waiting/approved request or a Premium grant. */
class NewPaymentReference implements ValidationRule
{
    /** @param  int|null  $ignoreRequestId  the request being edited */
    public function __construct(private readonly ?int $ignoreRequestId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && trim($value) !== '' && app(PaymentRequests::class)->referenceUsed($value, $this->ignoreRequestId)) {
            $fail('This payment reference was already used. Check that it is not the same payment twice.');
        }
    }
}
