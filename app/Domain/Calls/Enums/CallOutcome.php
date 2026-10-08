<?php

namespace App\Domain\Calls\Enums;

use App\Support\EnumHelpers;

enum CallOutcome: string
{
    use EnumHelpers;

    case CONNECTED = 'CONNECTED';
    case NO_ANSWER = 'NO_ANSWER';
    case CALLBACK_REQUESTED = 'CALLBACK_REQUESTED';
    case INVALID_NUMBER = 'INVALID_NUMBER';
    case NOT_INTERESTED = 'NOT_INTERESTED';

    public function label(): string
    {
        return match ($this) {
            self::CONNECTED => 'Joint',
            self::NO_ANSWER => 'Pas de réponse',
            self::CALLBACK_REQUESTED => 'Rappel demandé',
            self::INVALID_NUMBER => 'Numéro invalide',
            self::NOT_INTERESTED => 'Pas intéressé',
        };
    }

    /**
     * Outcomes meaning the dispatcher actually spoke with the customer.
     *
     * @return array<int, string>
     */
    public static function reachedValues(): array
    {
        return [self::CONNECTED->value, self::CALLBACK_REQUESTED->value, self::NOT_INTERESTED->value];
    }

    /**
     * Outcomes a dispatcher can register without completing the wizard.
     *
     * @return array<int, string>
     */
    public static function quickOutcomeValues(): array
    {
        return [self::NO_ANSWER->value, self::CALLBACK_REQUESTED->value, self::INVALID_NUMBER->value, self::NOT_INTERESTED->value];
    }
}
