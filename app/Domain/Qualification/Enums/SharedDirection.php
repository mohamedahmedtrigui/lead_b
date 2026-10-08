<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum SharedDirection: string
{
    use EnumHelpers;

    case OUTBOUND = 'OUTBOUND';
    case RETURN = 'RETURN';
    case BOTH = 'BOTH';
}
