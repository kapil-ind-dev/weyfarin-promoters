<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesEmbedWidget;
use App\Models\Experience;
use App\Support\EmbedPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Search results and experience detail, scoped to what a widget may expose.
 */
class EmbedCatalogController extends Controller
{
    use ResolvesEmbedWidget;

    /** GET /api/embed/{publicKey}/search.json?q=&destination=&page= */
    public function search(Request $request, string $publicKey): JsonResponse
    {
        $widget = $this->resolveWidget($publicKey);

        if ($refusal = $this->refuse($request, $widget)) {
            return $refusal;
        }

        // Normalise before caching so "Delhi", " delhi " and "DELHI" share one entry.
        $q           = Str::of((string) $request->query('q', ''))->squish()->lower()->limit(60, '')->toString();
        $destination = Str::limit(trim((string) $request->query('destination', '')), 80, '') ?: null;
        $page        = max(1, min(50, (int) $request->query('page', 1)));

        $cacheKey = $widget->cacheKey() . ':search:' . md5($q . '|' . $destination . '|' . $page);

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($widget, $q, $destination, $page) {
            $result    = $widget->searchExperiences($q, $destination, $page);
            $presenter = new EmbedPresenter($widget);

            return [
                'items'    => $result['items']->map(fn (Experience $e) => $presenter->card($e))->values()->all(),
                'total'    => $result['total'],
                'has_more' => $result['has_more'],
                'page'     => $result['page'],
            ];
        });

        return $this->embedJson($payload, 200, 'public, max-age=60, s-maxage=300');
    }

    /** GET /api/embed/{publicKey}/experiences/{slug}.json */
    public function show(Request $request, string $publicKey, string $slug): JsonResponse
    {
        $widget = $this->resolveWidget($publicKey);

        if ($refusal = $this->refuse($request, $widget)) {
            return $refusal;
        }

        // Short TTL: seats_left changes every time someone books.
        $payload = Cache::remember(
            $widget->cacheKey() . ':detail:' . $slug,
            now()->addSeconds(60),
            function () use ($widget, $slug) {
                $experience = $widget->findExperience($slug);

                return $experience
                    ? ['experience' => (new EmbedPresenter($widget))->detail($experience)]
                    : null;
            }
        );

        if (! $payload) {
            return $this->embedJson(['error' => 'experience_not_found'], 404);
        }

        return $this->embedJson($payload, 200, 'public, max-age=30, s-maxage=60');
    }
}
