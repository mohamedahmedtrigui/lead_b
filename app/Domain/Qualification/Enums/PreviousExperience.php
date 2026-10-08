<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum PreviousExperience: string
{
    use EnumHelpers;

    case YES = 'YES';
    case NO = 'NO';
    case UNKNOWN = 'UNKNOWN';
}
