<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetEvent extends Model
{
    public $timestamps = false;   // only created_at, written explicitly

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }
}
