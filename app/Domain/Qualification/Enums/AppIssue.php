<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum AppIssue: string
{
    use EnumHelpers;

    case PRICE = 'PRICE';
    case DELAYS = 'DELAYS';
    case CANCELLATIONS = 'CANCELLATIONS';
    case AVAILABILITY = 'AVAILABILITY';
    case DRIVER = 'DRIVER';
    case SAFETY = 'SAFETY';
    case VEHICLE = 'VEHICLE';
    case OTHER = 'OTHER';
}
