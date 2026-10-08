<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum Weekday: string
{
    use EnumHelpers;

    case MON = 'MON';
    case TUE = 'TUE';
    case WED = 'WED';
    case THU = 'THU';
    case FRI = 'FRI';
    case SAT = 'SAT';
    case SUN = 'SUN';
}
