<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum Frequency: string
{
    use EnumHelpers;

    case ONE_TIME = 'ONE_TIME';
    case SEVERAL_TIMES_PER_WEEK = 'SEVERAL_TIMES_PER_WEEK';
    case DAILY = 'DAILY';
    case FIXED_DAYS = 'FIXED_DAYS';
}
