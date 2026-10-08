<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum CallAvailability: string
{
    use EnumHelpers;

    case YES = 'YES';
    case CALLBACK = 'CALLBACK';
}
