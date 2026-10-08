<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum DecisionRole: string
{
    use EnumHelpers;

    case DECISION_MAKER = 'DECISION_MAKER';
    case INFLUENCER = 'INFLUENCER';
    case NEEDS_APPROVAL = 'NEEDS_APPROVAL';
}
