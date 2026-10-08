<?php

namespace App\Domain\Qualification\Enums;

use App\Domain\Leads\Enums\LeadStatus;
use App\Support\EnumHelpers;

enum NextAction: string
{
    use EnumHelpers;

    case QUALIFIED = 'QUALIFIED';
    case CALLBACK = 'CALLBACK';
    case SEND_QUOTATION = 'SEND_QUOTATION';
    case TRANSFER_TO_SALES = 'TRANSFER_TO_SALES';
    case FOLLOW_UP = 'FOLLOW_UP';
    case NOT_INTERESTED = 'NOT_INTERESTED';
    case NRP = 'NRP';

    /**
     * Lead status applied when a qualification is completed with this action.
     */
    public function leadStatus(): LeadStatus
    {
        return match ($this) {
            self::CALLBACK => LeadStatus::CALLBACK,
            self::NOT_INTERESTED => LeadStatus::NOT_INTERESTED,
            self::NRP => LeadStatus::NRP,
            default => LeadStatus::QUALIFIED,
        };
    }

    /**
     * Actions that close the conversation early: the full questionnaire is
     * not required to complete the qualification.
     */
    public function allowsPartialQualification(): bool
    {
        return in_array($this, [self::NOT_INTERESTED, self::NRP], true);
    }
}
