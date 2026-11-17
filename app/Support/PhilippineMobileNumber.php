<?php

namespace App\Support;

class PhilippineMobileNumber
{
    /**
     * Normalize a Philippine mobile number to +639XXXXXXXXX.
     *
     * Accepts 09XXXXXXXXX, 9XXXXXXXXX, 639XXXXXXXXX, and +639XXXXXXXXX,
     * including common spaces, dashes, and parentheses.
     */
    public static function normalize(string $phoneNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            $digits = '63'.substr($digits, 1);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '63'.$digits;
        }

        if (! preg_match('/^639\d{9}$/', $digits)) {
            return null;
        }

        return '+'.$digits;
    }

    /**
     * Return common stored representations for database matching.
     *
     * @return array<int, string>
     */
    public static function variants(string $normalizedNumber): array
    {
        $digits = ltrim($normalizedNumber, '+');

        return array_values(array_unique([
            $digits,
            '0'.substr($digits, 2),
            substr($digits, 2),
        ]));
    }

    public static function mask(string $normalizedNumber): string
    {
        return '+63 ••• ••• '.substr($normalizedNumber, -4);
    }
}
