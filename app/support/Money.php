<?php

namespace App\Support;

/**
 * Major ↔ minor unit conversion. Stripe charges in the currency's smallest
 * unit; zero-decimal currencies (JPY, KRW, …) have no subunit at all.
 */
final class Money
{
    private const ZERO_DECIMAL = [
        'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga',
        'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
    ];

    public static function factor(string $currency): int
    {
        return in_array(strtolower($currency), self::ZERO_DECIMAL, true) ? 1 : 100;
    }

    public static function toMinor(float|int|string|null $amount, string $currency): int
    {
        return (int) round(((float) $amount) * self::factor($currency));
    }

    public static function toMajor(int $minor, string $currency): float
    {
        return $minor / self::factor($currency);
    }
}