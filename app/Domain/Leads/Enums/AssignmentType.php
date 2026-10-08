<?php

namespace App\Domain\Leads\Enums;

use App\Support\EnumHelpers;

enum AssignmentType: string
{
    use EnumHelpers;

    case AUTO = 'AUTO';
    case MANUAL = 'MANUAL';
    case UNASSIGN = 'UNASSIGN';
}
