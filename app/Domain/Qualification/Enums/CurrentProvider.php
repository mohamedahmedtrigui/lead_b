<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum CurrentProvider: string
{
    use EnumHelpers;

    case NO = 'NO';
    case TRADITIONAL_TAXI = 'TRADITIONAL_TAXI';
    case APPLICATION = 'APPLICATION';
    case PRIVATE_TRANSPORT = 'PRIVATE_TRANSPORT';
    case TRANSPORT_COMPANY = 'TRANSPORT_COMPANY';
    case PRIVATE_DRIVER = 'PRIVATE_DRIVER';
    case OTHER = 'OTHER';
}
