<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Widget extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'filters'           => 'array',
        'theme'             => 'array',
        'show'              => 'array',
        'utm'               => 'array',
        'is_active'         => 'boolean',
        'restrict_domains'  => 'boolean',
        'last_served_at'    => 'datetime',
    ];

    /**
     * Widget types and the renderer file each one loads (public/r/{renderer}.js).
     * Category, featured and location share the listing renderer — they differ
     * only in which experiences the server selects (PLAN.md D4).
     */
    public const TYPES = [
        'listing'  => 'listing',
        'category' => 'listing',
        'featured' => 'listing',
        'location' => 'listing',
        'search'   => 'search',
        'detail'   => 'detail',
    ];

    /** Search widget page size. */
    public const SEARCH_PAGE_SIZE = 12;

    public const DEFAULT_THEME = [
        'accent'      => '#0f5c4d',
        'accent_text' => '#ffffff',
        'text'        => '#111111',
        'muted'       => '#6b7280',
        'surface'     => '#ffffff',
        'border'      => '#e5e7eb',
        'radius'      => '10px',
        'font'        => 'inherit',   // inherit = blend with the host page's typography
    ];

    public const DEFAULT_SHOW = [
        'price'    => true,
        'rating'   => true,
        'duration' => true,
        'location' => true,
        'distance' => true,   // location widgets only: "12 km away"
        'cta'      => true,
        'badge'    => true,
    ];

    /** Location widgets: default and hard cap on search radius. */
    public const DEFAULT_RADIUS_KM = 50;
    public const MAX_RADIUS_KM     = 1000;

    /** Rows pulled from the bounding box before exact distance filtering. */
    private const NEARBY_CANDIDATES = 200;

    protected static function booted(): void
    {
        static::creating(function (self $widget) {
            $widget->public_key ??= self::generateKey();
        });

        // Any config change bumps the version so CDN/browser caches invalidate.
        static::updating(function (self $widget) {
            if ($widget->isDirty() && ! $widget->isDirty('last_served_at')) {
                $widget->config_version = $widget->config_version + 1;
            }
        });
    }

    public static function generateKey(): string
    {
        $prefix = app()->isProduction() ? 'pk_live_' : 'pk_test_';

        return $prefix . bin2hex(random_bytes(16));
    }

    public function getRouteKeyName(): string
    {
        return 'public_key';
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WidgetItem::class)->orderBy('position');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(WidgetDomain::class);
    }

    public function renderer(): string
    {
        return self::TYPES[$this->type] ?? 'listing';
    }

    public function theme(): array
    {
        return array_merge(self::DEFAULT_THEME, $this->theme ?? []);
    }

    public function show(): array
    {
        return array_merge(self::DEFAULT_SHOW, $this->show ?? []);
    }

    /**
     * Resolve the experiences this widget renders, by type (PLAN.md D4).
     *
     * $context carries per-request input the widget config does not own —
     * today only the lat/lng a location widget receives from data-lat/data-lng.
     *
     * @return Collection<int, WidgetItem>
     */
    public function resolveExperiences(array $context = []): Collection
    {
        return match ($this->type) {
            // Curated picks. Falls back to top-rated so a fresh widget is never blank.
            'featured' => $this->curated()->whenEmpty(fn () => $this->dynamic('rating_desc')),

            // Needs at least one category, otherwise it would silently become
            // "everything" — worse than rendering nothing.
            'category' => empty($this->filters['category_ids']) ? collect() : $this->dynamic(),

            'location' => $this->nearby($context),

            // Single experience; the booking renderer takes over from here.
            'detail'   => $this->curated()->take(1)->values(),

            // First page of the catalogue; later pages come from /search.json.
            'search'   => $this->wrap($this->searchExperiences()['items']),

            default    => $this->selection_mode === 'manual' ? $this->curated() : $this->dynamic(),
        };
    }

    /** Hand-picked items, in dashboard order. */
    private function curated(): Collection
    {
        return $this->items()
            ->with('experience.media')
            ->get()
            ->filter(fn (WidgetItem $item) => $item->experience && $item->experience->isPublished())
            ->take($this->max_items)
            ->values();
    }

    /** Rule-based selection from filters + sort. */
    private function dynamic(?string $sortOverride = null): Collection
    {
        $query = $this->filteredQuery();

        match ($sortOverride ?? $this->sort) {
            'price_asc'   => $query->orderBy('price_from'),
            'price_desc'  => $query->orderByDesc('price_from'),
            'newest'      => $query->latest('published_at'),
            default       => $query->orderByDesc('rating')->orderByDesc('review_count'),
        };

        return $this->wrap($query->with('media')->take($this->max_items)->get());
    }

    /**
     * Experiences within radius_km of a point, nearest first.
     *
     * Two stages: a bounding box in SQL (index-friendly, portable — SQLite
     * has no trig functions), then exact haversine distance in PHP.
     */
    private function nearby(array $context): Collection
    {
        $filters = $this->filters ?? [];

        // The page's data-lat/data-lng wins, so one key serves every property
        // of a hotel chain. Config coordinates are the fallback.
        $lat = $context['lat'] ?? $filters['lat'] ?? null;
        $lng = $context['lng'] ?? $filters['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return collect();
        }

        $lat    = (float) $lat;
        $lng    = (float) $lng;
        $radius = (float) ($filters['radius_km'] ?? self::DEFAULT_RADIUS_KM);
        $radius = max(1.0, min($radius, self::MAX_RADIUS_KM));

        // 1° latitude ≈ 111.045 km everywhere; 1° longitude shrinks with cos(lat).
        $dLat = $radius / 111.045;
        $dLng = $radius / (111.045 * max(cos(deg2rad($lat)), 0.01));

        $candidates = $this->filteredQuery()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $dLat, $lat + $dLat])
            ->whereBetween('longitude', [$lng - $dLng, $lng + $dLng])
            ->with('media')
            ->take(self::NEARBY_CANDIDATES)
            ->get();

        $nearest = $candidates
            ->each(fn (Experience $e) => $e->setAttribute(
                'distance_km',
                self::distanceKm($lat, $lng, (float) $e->latitude, (float) $e->longitude)
            ))
            ->filter(fn (Experience $e) => $e->distance_km <= $radius)
            ->sortBy('distance_km')
            ->take($this->max_items)
            ->values();

        return $this->wrap($nearest);
    }

    /**
     * Every experience this widget is allowed to expose — the scope for the
     * search box and for the detail endpoint. A partner who curated three
     * experiences must not be able to open a fourth by editing a URL.
     */
    public function catalogQuery(): Builder
    {
        $curatedOnly = in_array($this->type, ['featured', 'detail'], true)
            || ($this->type === 'listing' && $this->selection_mode === 'manual');

        if ($curatedOnly) {
            return Experience::query()->published()
                ->whereIn('id', WidgetItem::where('widget_id', $this->id)->select('experience_id'));
        }

        return $this->filteredQuery();
    }

    /**
     * @return array{items: \Illuminate\Support\Collection, total: int, has_more: bool, page: int}
     */
    public function searchExperiences(string $q = '', ?string $destination = null, int $page = 1): array
    {
        $query = $this->catalogQuery();

        // Strip LIKE wildcards rather than escaping them — escaping needs an
        // ESCAPE clause that SQLite and MySQL spell differently.
        $q = trim(preg_replace('/[%_\\\\]/', '', $q));

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function (Builder $w) use ($like) {
                $w->where('title', 'like', $like)
                  ->orWhere('city', 'like', $like)
                  ->orWhere('region', 'like', $like)
                  ->orWhere('country', 'like', $like)
                  ->orWhere('summary', 'like', $like);
            });
        }

        // Destinations are regions where we have one, cities where we don't —
        // so match either.
        if ($destination) {
            $query->where(function (Builder $w) use ($destination) {
                $w->where('region', $destination)->orWhere('city', $destination);
            });
        }

        $total = (clone $query)->count();

        $items = $query->with('media')
            ->orderByDesc('rating')
            ->orderByDesc('review_count')
            ->orderBy('id')                 // stable order across pages
            ->forPage($page, self::SEARCH_PAGE_SIZE)
            ->get();

        return [
            'items'    => $items,
            'total'    => $total,
            'has_more' => $page * self::SEARCH_PAGE_SIZE < $total,
            'page'     => $page,
        ];
    }

    /**
     * "All destinations" dropdown: region where set (Kerala groups Alappuzha
     * and Palakkad), city otherwise.
     */
    public function destinations(): array
    {
        return $this->catalogQuery()
            ->get(['region', 'city'])
            ->map(fn (Experience $e) => $e->region ?: $e->city)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function findExperience(string $slug): ?Experience
    {
        return $this->catalogQuery()
            ->where('slug', $slug)
            ->with(['media', 'itinerary'])
            ->first();
    }

    /** Filters shared by every dynamic selection. */
    private function filteredQuery(): Builder
    {
        $filters = $this->filters ?? [];
        $query   = Experience::query()->published();

        if (! empty($filters['category_ids'])) $query->whereIn('category_id', (array) $filters['category_ids']);
        if (! empty($filters['city']))         $query->where('city', $filters['city']);
        if (! empty($filters['country']))      $query->where('country', $filters['country']);
        if (! empty($filters['min_rating']))   $query->where('rating', '>=', $filters['min_rating']);
        if (! empty($filters['price_max']))    $query->where('price_from', '<=', $filters['price_max']);

        return $query;
    }

    /** Dynamic results go through the same WidgetItem shape as curated ones. */
    private function wrap(Collection $experiences): Collection
    {
        return $experiences->map(function (Experience $experience) {
            $item = new WidgetItem(['experience_id' => $experience->id]);
            $item->setRelation('experience', $experience);

            return $item;
        })->values();
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * 6371.0 * asin(min(1.0, sqrt($a)));
    }

    /**
     * Domain allowlist. Spoofable (it's a request header), so treat this as
     * anti-hotlinking hygiene, not security — the payload is public data anyway.
     */
    public function allowsHost(?string $host): bool
    {
        if (! $this->restrict_domains) {
            return true;
        }

        if (! $host) {
            return false;
        }

        $host = Str::of($host)->lower()->replaceFirst('www.', '')->toString();

        return $this->domains->contains(function (WidgetDomain $domain) use ($host) {
            $pattern = Str::of($domain->domain)->lower()->replaceFirst('www.', '')->toString();

            return $pattern === $host || Str::is($pattern, $host);
        });
    }

    /**
     * Payload cache key. Location context is part of it — two hotel pages
     * with different coordinates must not share a result.
     */
    public function cacheKey(array $context = []): string
    {
        $key = "widget:{$this->public_key}:v{$this->config_version}";

        if (isset($context['lat'], $context['lng'])) {
            $key .= ':' . $context['lat'] . ',' . $context['lng'];
        }

        return $key;
    }
}
