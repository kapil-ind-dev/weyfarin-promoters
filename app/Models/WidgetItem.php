<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'overrides' => 'array',
    ];

    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    /** Per-widget copy override falls back to the listing's own value. */
    public function value(string $key, mixed $fallback = null): mixed
    {
        return data_get($this->overrides, $key) ?? $fallback;
    }
}
