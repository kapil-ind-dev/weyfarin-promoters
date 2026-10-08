<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = ['id'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}