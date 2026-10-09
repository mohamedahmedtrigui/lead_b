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

    /**
     * Cannot be combined with another action.
     */
    public function isExclusive(): bool
    {
        return $this->allowsPartialQualification();
    }

    /**
     * Several actions can be chosen at closing; the main one drives the lead
     * status: NRP > not interested > callback > quote > sales > follow-up > qualified.
     *
     * @param  array<int, string|self>  $actions
     */
    public static function primary(array $actions): ?self
    {
        $chosen = array_map(fn ($a) => $a instanceof self ? $a : self::tryFrom((string) $a), $actions);

        foreach ([self::NRP, self::NOT_INTERESTED, self::CALLBACK, self::SEND_QUOTATION, self::TRANSFER_TO_SALES, self::FOLLOW_UP, self::QUALIFIED] as $action) {
            if (in_array($action, $chosen, true)) {
                return $action;
            }
        }

        return null;
    }
}
