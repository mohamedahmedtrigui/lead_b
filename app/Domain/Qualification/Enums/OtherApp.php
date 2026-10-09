<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum OtherApp: string
{
    use EnumHelpers;

    case BOLT = 'BOLT';
    case YASSIR = 'YASSIR';
    case INDRIVE = 'INDRIVE';
    case OTHER = 'OTHER';
}
