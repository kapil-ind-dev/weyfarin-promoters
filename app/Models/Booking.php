<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['access_token_hash'];

    protected $casts = [
        'adults'                => 'integer',
        'children'              => 'integer',
        'adult_unit_minor'      => 'integer',
        'child_unit_minor'      => 'integer',
        'gross_minor'           => 'integer',
        'provider_share_bp'     => 'integer',
        'partner_commission_bp' => 'integer',
        'hold_expires_at'       => 'datetime',
        'confirmed_at'          => 'datetime',
    ];

    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(ExperienceDeparture::class, 'departure_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function splits(): HasMany
    {
        return $this->hasMany(BookingSplit::class);
    }

    public function guests(): int
    {
        return (int) $this->adults + (int) $this->children;
    }

    public function holdSecondsLeft(): int
    {
        // Carbon 3 returns a signed float here; negative once the hold has lapsed.
        return $this->hold_expires_at ? max(0, (int) floor(now()->diffInSeconds($this->hold_expires_at, false))) : 0;
    }

    /** Constant-time token check — the widget's only proof it owns this booking. */
    public function tokenMatches(?string $token): bool
    {
        return is_string($token) && $token !== ''
            && hash_equals($this->access_token_hash, hash('sha256', $token));
    }
}