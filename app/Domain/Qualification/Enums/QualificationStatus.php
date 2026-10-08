<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum QualificationStatus: string
{
    use EnumHelpers;

    case DRAFT = 'DRAFT';
    case COMPLETED = 'COMPLETED';
}
