<?php

namespace App\Domain\Qualification\Enums;

use App\Support\EnumHelpers;

enum InterestLevel: string
{
    use EnumHelpers;

    case HOT = 'HOT';
    case WARM = 'WARM';
    case INTERESTED = 'INTERESTED';
    case LOW = 'LOW';

    public static function fromScore(int $score): self
    {
        $levels = config('qualification.levels');

        return match (true) {
            $score >= $levels['HOT'] => self::HOT,
            $score >= $levels['WARM'] => self::WARM,
            $score >= $levels['INTERESTED'] => self::INTERESTED,
            default => self::LOW,
        };
    }
}
