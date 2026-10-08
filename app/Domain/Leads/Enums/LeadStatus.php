<?php

namespace App\Domain\Leads\Enums;

use App\Support\EnumHelpers;

enum LeadStatus: string
{
    use EnumHelpers;

    case PENDING = 'PENDING';
    case IN_PROGRESS = 'IN_PROGRESS';
    case CALLBACK = 'CALLBACK';
    case QUALIFIED = 'QUALIFIED';
    case CONVERTED = 'CONVERTED';
    case NRP = 'NRP';
    case NOT_INTERESTED = 'NOT_INTERESTED';
    case INVALID = 'INVALID';

    /**
     * Statuses still worked by dispatchers (released back to the pool when a
     * dispatcher is deactivated).
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::PENDING, self::IN_PROGRESS, self::CALLBACK, self::NRP];
    }

    /**
     * Display order of lead lists, from the most important to work on to
     * the least (closed leads last).
     *
     * @return array<int, self>
     */
    public static function byImportance(): array
    {
        return [
            self::PENDING,
            self::IN_PROGRESS,
            self::CALLBACK,
            self::NRP,
            self::QUALIFIED,
            self::CONVERTED,
            self::NOT_INTERESTED,
            self::INVALID,
        ];
    }

    /**
     * Statuses on which a dispatcher can no longer start a call.
     */
    public function isClosedForDispatcher(): bool
    {
        return in_array($this, [self::CONVERTED, self::INVALID, self::NOT_INTERESTED], true);
    }
}
