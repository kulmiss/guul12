<?php

namespace App\Services\Branding;

/**
 * The branding cannot be saved; $problems says why, in easy words.
 */
final class BrandingInvalid extends \RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
