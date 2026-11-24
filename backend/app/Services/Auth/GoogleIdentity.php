<?php

namespace App\Services\Auth;

final readonly class GoogleIdentity
{
    public function __construct(
        public string $sub,
        public ?string $email,
        public bool $emailVerified,
    ) {}
}
