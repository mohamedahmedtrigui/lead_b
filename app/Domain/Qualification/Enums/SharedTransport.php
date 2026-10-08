<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum SharedTransport: string
{
    use EnumHelpers;

    case YES = 'YES';
    case MAYBE = 'MAYBE';
    case NO = 'NO';
}
