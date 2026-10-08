<?php

namespace App\Console\Commands;

use App\Services\Booking\BookingService;
use Illuminate\Console\Command;

/**
 * Frees seats from abandoned checkouts. Scheduled every minute in
 * routes/console.php. Booking creation also expires holds lazily per
 * departure, so availability stays correct even if the scheduler is not
 * running locally — this just keeps seats_left honest for everyone else.
 */
class ReleaseExpiredHolds extends Command
{
    protected $signature = 'bookings:release-expired';
    protected $description = 'Release seat holds from checkouts that were never paid';
    public function handle(BookingService $bookings): int
    {
        $released = $bookings->expireDue();
        $this->info("Released {$released} expired " . ($released === 1 ? 'hold' : 'holds') . '.');
        return self::SUCCESS;
    }
}