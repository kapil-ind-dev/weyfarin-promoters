<?php

namespace App\Services\Booking;

use RuntimeException;

/**
 * A booking request the guest can act on (sold out, bad input). Carries the
 * machine code the widget switches on and the HTTP status to return.
 */
class BookingException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }
}