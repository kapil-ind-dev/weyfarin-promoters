<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceDeparture extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
    ];

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'open')
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time');
    }

    public function seatsLeft(): int
    {
        return max(0, $this->seats_total - $this->seats_held - $this->seats_sold);
    }
}
