<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesEmbedWidget;
use App\Models\Widget;
use App\Models\WidgetEvent;
use App\Models\WidgetItem;
use App\Support\EmbedPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EmbedController extends Controller
{
    use ResolvesEmbedWidget;

    /** Event types the beacon may record. */
    private const EVENT_TYPES = ['impression', 'click', 'detail'];

    /**
     * GET /api/embed/{publicKey}.json
     *
     * Public, cacheable, CORS-open. Contains only data that is already public
     * on weyfarin.com — never anything partner-private.
     */
    public function config(Request $request, string $publicKey): JsonResponse
    {
        $widget = $this->resolveWidget($publicKey);

        if ($refusal = $this->refuse($request, $widget)) {
            return $refusal;
        }

        $context = $this->context($request, $widget);

        $payload = Cache::remember(
            $widget->cacheKey($context),
            now()->addMinutes(30),
            fn () => $this->buildPayload($widget, $context)
        );

        // 5 min in the browser, 1 hour at the CDN, stale served for a day if origin is down.
        return $this->embedJson($payload, 200, 'public, max-age=300, s-maxage=3600, stale-while-revalidate=86400')
            ->header('ETag', '"' . md5($widget->cacheKey($context)) . '"');
    }

    /**
     * GET /embed/{publicKey}/frame
     *
     * Iframe fallback for hosts that strip <script>. Renders cards server-side.
     * Search and detail types degrade to a plain card grid here.
     */
    public function frame(Request $request, string $publicKey): Response
    {
        $widget = $this->resolveWidget($publicKey);

        abort_if(! $widget, 404);
        abort_if(! $widget->allowsHost($this->refHost($request)), 403);

        $context = $this->context($request, $widget);

        $payload = Cache::remember(
            $widget->cacheKey($context),
            now()->addMinutes(30),
            fn () => $this->buildPayload($widget, $context)
        );

        return response()
            ->view('embed.frame', ['payload' => $payload])
            ->withHeaders([
                'Content-Security-Policy' => 'frame-ancestors *',
                'Cache-Control'           => 'public, max-age=300, s-maxage=3600',
            ]);
    }

    /**
     * POST /api/embed/{publicKey}/events
     *
     * Fired via navigator.sendBeacon as text/plain (no preflight). Always 204.
     */
    public function track(Request $request, string $publicKey): Response
    {
        $widget = $this->resolveWidget($publicKey);

        if (! $widget) {
            return response()->noContent(204)->header('Access-Control-Allow-Origin', '*');
        }

        $decoded = json_decode($request->getContent(), true);
        $events  = collect(is_array($decoded) ? ($decoded['events'] ?? []) : [])->take(50);

        $rows = $events->map(function ($event) use ($widget, $request) {
            if (! is_array($event) || ! in_array($event['type'] ?? '', self::EVENT_TYPES, true)) {
                return null;
            }

            return [
                'widget_id'     => $widget->id,
                'experience_id' => isset($event['id']) ? (int) $event['id'] : null,
                'type'          => $event['type'],
                'ref_host'      => Str::limit($this->refHost($request) ?? '', 190, ''),
                'ref_path'      => Str::limit((string) ($event['path'] ?? ''), 250, ''),
                'visitor_hash'  => $this->visitorHash($request),
                'device'        => $this->device($request),
                'created_at'    => now(),
            ];
        })->filter()->all();

        if ($rows) {
            // After the response is flushed — no queue worker required.
            app()->terminating(function () use ($rows) {
                WidgetEvent::insert($rows);
            });
        }

        return response()->noContent(204)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }

    // ------------------------------------------------------------------

    private function buildPayload(Widget $widget, array $context = []): array
    {
        $base      = rtrim(config('app.url'), '/');
        $presenter = new EmbedPresenter($widget);

        $items = $widget->resolveExperiences($context)
            ->map(fn (WidgetItem $item) => $presenter->card($item->experience, $item))
            ->values()
            ->all();

        $payload = [
            'widget' => [
                'key'       => $widget->public_key,
                'version'   => $widget->config_version,
                'type'      => $widget->type ?? 'listing',
                'renderer'  => $widget->renderer(),
                'heading'   => $widget->heading,
                'layout'    => $widget->layout,
                'columns'   => (int) $widget->columns,
                'target'    => $widget->link_target,
                'cta'       => $widget->cta_label,
                'theme'     => $widget->theme(),
                'show'      => $widget->show(),
                'brand_url' => $base,
                'api_url'   => $base . '/api/embed/' . $widget->public_key,
                'track_url' => $base . '/api/embed/' . $widget->public_key . '/events',
            ],
            'items' => $items,
        ];

        if ($widget->type === 'search') {
            $first = $widget->searchExperiences();

            $payload['search'] = [
                'total'    => $first['total'],
                'has_more' => $first['has_more'],
                'page'     => 1,
            ];
            $payload['facets'] = [
                'destinations' => $widget->destinations(),
            ];
        }

        if ($widget->type === 'detail' && $items) {
            $experience = $widget->findExperience($items[0]['slug']);
            $payload['experience'] = $experience ? $presenter->detail($experience) : null;
        }

        return $payload;
    }

    /**
     * Per-request input the widget config does not own. Only location widgets
     * read it, rounded to 2 decimals (~1.1 km) so the cache holds one entry
     * per neighbourhood. Other types ignore it — it must not fan out their cache.
     */
    private function context(Request $request, Widget $widget): array
    {
        if ($widget->type !== 'location') {
            return [];
        }

        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return [];
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return [];
        }

        return ['lat' => round($lat, 2), 'lng' => round($lng, 2)];
    }

    private function visitorHash(Request $request): string
    {
        // Rotating daily salt: uniques per day, no way to re-identify later.
        return hash('sha256', implode('|', [
            $request->ip(),
            $request->userAgent(),
            now()->toDateString(),
            config('app.key'),
        ]));
    }

    private function device(Request $request): string
    {
        $agent = strtolower((string) $request->userAgent());

        return match (true) {
            str_contains($agent, 'ipad'), str_contains($agent, 'tablet') => 'tablet',
            str_contains($agent, 'mobi'), str_contains($agent, 'android') => 'mobile',
            default => 'desktop',
        };
    }
}