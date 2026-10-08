<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum TransportNeed: string
{
    use EnumHelpers;

    case PERSONAL = 'PERSONAL';
    case FAMILY = 'FAMILY';
    case SHARED = 'SHARED';
    case EMPLOYEE = 'EMPLOYEE';
    case STUDENT = 'STUDENT';
    case RECURRING = 'RECURRING';
    case OTHER = 'OTHER';
}
