<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum PurchaseDriver: string
{
    use EnumHelpers;

    case PRICE = 'PRICE';
    case PUNCTUALITY = 'PUNCTUALITY';
    case COMFORT = 'COMFORT';
    case RELIABILITY = 'RELIABILITY';
    case ORGANIZATION = 'ORGANIZATION';
}
