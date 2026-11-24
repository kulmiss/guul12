<?php

namespace App\Nova\Cards;

use Laravel\Nova\Card;

/**
 * "Find student by ID" on the payments dashboard: type the ID a student sent (ST-7K3Q9, st 7k3q9, …)
 * and jump to the Students list with that search. Drawn by resources/nova/guul-nova.js.
 */
class FindStudent extends Card
{
    public $width = '1/3';

    public function component(): string
    {
        return 'guul-find-student';
    }
}
