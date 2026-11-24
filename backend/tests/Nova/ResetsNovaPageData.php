<?php

namespace Tests\Nova;

use Laravel\Nova\Nova;

/**
 * Nova builds its page data (menu, logo, user ID) once per PHP process and keeps it in a static array.
 * In production every request is a new process; in tests many requests share one, so we clear it first.
 */
trait ResetsNovaPageData
{
    protected function setUpResetsNovaPageData(): void
    {
        Nova::$jsonVariables = [];
    }
}
