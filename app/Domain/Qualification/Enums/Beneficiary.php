<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum Beneficiary: string
{
    use EnumHelpers;

    case SELF = 'SELF';
    case FAMILY = 'FAMILY';
    case EMPLOYEES = 'EMPLOYEES';
    case STUDENTS = 'STUDENTS';
    case COMPANY = 'COMPANY';
    case OTHER = 'OTHER';
}
