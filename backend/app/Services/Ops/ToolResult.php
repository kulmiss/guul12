<?php

namespace App\Services\Ops;

/**
 * What a staff tool did: worked or not, and a sentence for the staff member.
 */
final class ToolResult
{
    public function __construct(public readonly bool $ok, public readonly string $message) {}
}
