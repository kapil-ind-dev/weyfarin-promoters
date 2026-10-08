<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WidgetDomain extends Model
{
    protected $guarded = ['id'];

    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }

    public function setDomainAttribute(string $value): void
    {
        $this->attributes['domain'] = Str::of($value)
            ->lower()
            ->trim()
            ->replaceMatches('#^https?://#', '')
            ->before('/')
            ->replaceFirst('www.', '')
            ->toString();
    }
}
