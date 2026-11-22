<?php

namespace App\Content;

use RuntimeException;

/**
 * The content folder failed the automatic checks. Nothing was imported.
 */
final class ContentInvalid extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $warnings,
    ) {
        parent::__construct(count($errors).' content error(s): '.implode(' | ', array_slice($errors, 0, 5)));
    }
}
