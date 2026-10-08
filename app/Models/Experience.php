<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experience extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price_from'   => 'decimal:2',
        'rating'       => 'decimal:2',
        'published_at' => 'datetime',
        'latitude'     => 'float',
        'longitude'    => 'float',
        'languages'    => 'array',
        'good_to_know' => 'array',
        'child_price_percent' => 'integer',
        'min_age'      => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ExperienceMedia::class)->orderBy('position');
    }

    public function itinerary(): HasMany
    {
        return $this->hasMany(ExperienceItineraryItem::class)->orderBy('position');
    }

    public function departures(): HasMany
    {
        return $this->hasMany(ExperienceDeparture::class);
    }

    public function widgetItems(): HasMany
    {
        return $this->hasMany(WidgetItem::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** Children are 2–12; an experience with a minimum age above 12 takes none. */
    public function childrenAllowed(): bool
    {
        return $this->min_age === null || $this->min_age <= 12;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at
            && $this->published_at->isPast();
    }

    /**
     * First media row, else the cover column. Swap the body for your CDN's
     * resize syntax — Cloudinary, imgproxy, Bunny, whatever you run.
     */
    public function coverImageUrl(int $width = 800, int $height = 600): ?string
    {
        $url = $this->relationLoaded('media')
            ? optional($this->media->first())->url
            : optional($this->media()->first())->url;

        $url ??= $this->cover_image;

        if (! $url) {
            return null;
        }

        // Example for a resizing CDN:
        // return "https://cdn.weyfarin.com/{$width}x{$height}/" . ltrim(parse_url($url, PHP_URL_PATH), '/');

        return $url;
    }

    /** "4 hrs", "2 days", "1 day 6 hrs" */
    public function durationLabel(): ?string
    {
        $minutes = (int) $this->duration_minutes;

        if ($minutes <= 0) {
            return null;
        }

        $days  = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        $parts = [];

        if ($days)  $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
        if ($hours) $parts[] = $hours . ' ' . ($hours === 1 ? 'hr' : 'hrs');

        return implode(' ', $parts);
    }
}