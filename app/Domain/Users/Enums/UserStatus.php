<?php

namespace App\Domain\Users\Enums;

use App\Support\EnumHelpers;

enum UserStatus: string
{
    use EnumHelpers;

    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case DEACTIVATED = 'DEACTIVATED';
}
