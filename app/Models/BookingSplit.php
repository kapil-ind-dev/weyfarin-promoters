<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only (PLAN.md D13). Corrections are new rows, never edits.
 */
class BookingSplit extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('booking_splits is append-only — write a reversal row instead.');
        });

        static::deleting(function () {
            throw new LogicException('booking_splits is append-only — write a reversal row instead.');
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}