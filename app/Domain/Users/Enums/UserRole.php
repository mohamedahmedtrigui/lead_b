<?php

namespace App\Domain\Users\Enums;

use App\Support\EnumHelpers;

enum UserRole: string
{
    use EnumHelpers;

    case ADMIN = 'ADMIN';
    case DISPATCHER = 'DISPATCHER';
}
