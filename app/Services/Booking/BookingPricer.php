<?php

namespace App\Services\Booking;

use App\Models\Experience;
use App\Models\ExperienceDeparture;
use App\Support\Money;

/**
 * The only place a booking price is computed. The widget shows the same
 * numbers, but the server never trusts them.
 */
class BookingPricer
{
    /**
     * @return array{adult_unit_minor:int, child_unit_minor:int, gross_minor:int, currency:string}
     */
    public function quote(Experience $experience, ExperienceDeparture $departure, int $adults, int $children): array
    {
        $currency = strtolower($experience->currency ?: 'INR');

        $adult = $departure->price_override_minor ?? Money::toMinor($experience->price_from, $currency);

        $child = $experience->child_price_percent !== null
            ? (int) round($adult * $experience->child_price_percent / 100)
            : $adult;

        return [
            'adult_unit_minor' => $adult,
            'child_unit_minor' => $child,
            'gross_minor'      => $adults * $adult + $children * $child,
            'currency'         => $currency,
        ];
    }
}