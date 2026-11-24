<?php

namespace App\Enums;

/**
 * Whether an item (video, mock, …) is free or Premium.
 */
enum Access: string
{
    case Free = 'free';
    case Premium = 'premium';
}
