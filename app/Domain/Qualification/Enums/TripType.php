<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum TripType: string
{
    use EnumHelpers;

    case ONE_WAY = 'ONE_WAY';
    case ROUND_TRIP = 'ROUND_TRIP';
}
