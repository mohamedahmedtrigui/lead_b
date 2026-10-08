<?php

namespace App\Domain\Leads\Support;

/**
 * Normalizes phone numbers to E.164 (+21612345678) so duplicates are detected
 * regardless of how the number was typed in the source file.
 */
class PhoneNormalizer
{
    public function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $hasPlus = str_starts_with($value, '+');
        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '' || $digits === null) {
            return null;
        }

        $countryCode = (string) config('leads.import.default_country_code');
        $localLength = (int) config('leads.import.local_number_length');

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        if ($hasPlus) {
            return '+'.$digits;
        }

        if (strlen($digits) === $localLength) {
            return '+'.$countryCode.$digits;
        }

        if (str_starts_with($digits, $countryCode) && strlen($digits) === strlen($countryCode) + $localLength) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }
}
