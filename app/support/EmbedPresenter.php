<?php

namespace App\Support;

use App\Models\Experience;
use App\Models\ExperienceDeparture;
use App\Models\ExperienceItineraryItem;
use App\Models\Widget;
use App\Models\WidgetItem;
use App\Services\Booking\BookingPricer;
use Illuminate\Support\Str;

/**
 * The only place that decides what an experience looks like on the wire.
 * Config, search and detail endpoints all go through here, so a card in the
 * search grid and the same card in a featured strip can never disagree.
 *
 * Everything returned is plain text. Renderers use textContent, never
 * innerHTML, so nothing here is ever interpreted as markup.
 */
class EmbedPresenter
{
    public function __construct(private Widget $widget)
    {
    }

    public function card(Experience $experience, ?WidgetItem $item = null): array
    {
        $override = fn (string $key, mixed $fallback) => $item ? $item->value($key, $fallback) : $fallback;

        return [
            'id'       => $experience->id,
            'slug'     => $experience->slug,
            'title'    => $override('title', $experience->title),
            'summary'  => Str::limit(strip_tags((string) $experience->summary), 110),
            'image'    => $override('image', $experience->coverImageUrl(800, 600)),
            'location' => $this->location($experience),
            'duration' => $experience->durationLabel(),
            'price'    => (float) $override('price_from', $experience->price_from),
            'currency' => $experience->currency ?: 'INR',
            'rating'   => $experience->rating ? round((float) $experience->rating, 1) : null,
            'reviews'  => (int) ($experience->review_count ?? 0),
            'badge'    => $item?->badge,
            'distance' => $experience->distance_km !== null ? round($experience->distance_km, 1) : null,
            'url'      => $this->canonicalUrl($experience),
        ];
    }

    public function detail(Experience $experience): array
    {
        $card = $this->card($experience);

        $images = $experience->media->pluck('url')->filter()->take(5)->values()->all();
        if (! $images && $card['image']) {
            $images = [$card['image']];
        }

        $description = collect(preg_split('/\R{2,}/', trim(strip_tags((string) ($experience->description ?: $experience->summary)))))
            ->map(fn ($paragraph) => trim(preg_replace('/\s+/', ' ', $paragraph)))
            ->filter()
            ->values()
            ->all();

        // Same arithmetic as BookingPricer, so the card total and the charged
        // total cannot disagree.
        $pricer   = new BookingPricer();
        $currency = strtolower($experience->currency ?: 'INR');

        $departures = $experience->departures()->upcoming()->take(30)->get()
            ->map(function (ExperienceDeparture $departure) use ($pricer, $experience, $currency) {
                $quote = $pricer->quote($experience, $departure, 1, 1);

                return [
                    'id'          => $departure->id,
                    'date'        => $departure->date->toDateString(),
                    'label'       => $departure->date->format('D, j M') . ($departure->start_time ? ', ' . $departure->start_time : ''),
                    'seats_left'  => $departure->seatsLeft(),
                    'price'       => Money::toMajor($quote['adult_unit_minor'], $currency),
                    'child_price' => Money::toMajor($quote['child_unit_minor'], $currency),
                ];
            })
            ->values()
            ->all();

        $childPrice = $experience->child_price_percent !== null
            ? round($card['price'] * $experience->child_price_percent / 100, 2)
            : $card['price'];

        return $card + [
            'description'   => $description,
            'images'        => $images,
            'highlights'    => $this->highlights($experience, $card),
            'itinerary'     => $experience->itinerary
                ->map(fn (ExperienceItineraryItem $stop) => [
                    'title'       => $stop->title,
                    'description' => (string) $stop->description,
                    'duration'    => self::minutesLabel($stop->duration_minutes),
                ])
                ->values()
                ->all(),
            'meeting_point' => $this->meetingPoint($experience, $card),
            'host'          => $experience->host_name ? [
                'name'     => $experience->host_name,
                'bio'      => (string) $experience->host_bio,
                'avatar'   => $experience->host_avatar,
                'initials' => Str::upper(collect(explode(' ', $experience->host_name))
                    ->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join('')),
            ] : null,
            'good_to_know'  => collect($experience->good_to_know ?? [])
                ->map(fn ($row) => [
                    'title' => (string) ($row['title'] ?? ''),
                    'body'  => (string) ($row['body'] ?? ''),
                ])
                ->filter(fn ($row) => $row['title'] !== '')
                ->values()
                ->all(),
            'departures'       => $departures,
            'max_guests'       => (int) ($experience->group_size_max ?: 12),
            'child_price'      => $childPrice,
            'min_age'          => $experience->min_age,
            'children_allowed' => $experience->childrenAllowed(),
        ];
    }

    public function canonicalUrl(Experience $experience): string
    {
        $base = rtrim(config('app.url'), '/');
        $utm  = array_filter($this->widget->utm ?? []);

        $query = array_filter([
            'utm_source'   => $utm['source']   ?? 'embed',
            'utm_medium'   => $utm['medium']   ?? 'widget',
            'utm_campaign' => $utm['campaign'] ?? null,
            'wk'           => $this->widget->public_key,
            'aff'          => $this->widget->affiliate_code,
        ]);

        return $base . '/experience/' . $experience->slug . '?' . http_build_query($query);
    }

    public static function minutesLabel(?int $minutes): ?string
    {
        if (! $minutes) {
            return null;
        }

        $days  = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins  = $minutes % 60;

        $parts = [];
        if ($days)  $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
        if ($hours) $parts[] = $hours . ' ' . ($hours === 1 ? 'hr' : 'hrs');
        if ($mins && ! $days) $parts[] = $mins . ' min';

        return implode(' ', $parts);
    }

    private function location(Experience $experience): string
    {
        return trim(collect([$experience->city, $experience->country])->filter()->join(', '));
    }

    private function highlights(Experience $experience, array $card): array
    {
        $languages = collect($experience->languages ?? [])->filter()->join(', ');

        return collect([
            ['label' => 'Duration',   'value' => $card['duration']],
            ['label' => 'Group size', 'value' => $experience->group_size_max ? 'Up to ' . $experience->group_size_max : null],
            ['label' => 'Languages',  'value' => $languages ?: null],
            ['label' => 'Location',   'value' => $card['location'] ?: null],
        ])->filter(fn ($row) => $row['value'])->values()->all();
    }

    private function meetingPoint(Experience $experience, array $card): ?array
    {
        $hasCoords = $experience->latitude !== null && $experience->longitude !== null;

        if (! $hasCoords && ! $experience->meeting_point) {
            return null;
        }

        return [
            'label' => $experience->meeting_point ?: $card['location'],
            'lat'   => $hasCoords ? (float) $experience->latitude : null,
            'lng'   => $hasCoords ? (float) $experience->longitude : null,
        ];
    }
}