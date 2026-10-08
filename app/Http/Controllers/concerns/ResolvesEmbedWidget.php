<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Widget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Shared by every public embed endpoint: widget lookup, the domain allowlist,
 * and CORS-open JSON responses.
 */
trait ResolvesEmbedWidget
{
    protected function resolveWidget(string $publicKey): ?Widget
    {
        return Cache::remember(
            "widget:lookup:{$publicKey}",
            now()->addMinutes(10),
            fn () => Widget::with('domains')
                ->where('public_key', $publicKey)
                ->where('is_active', true)
                ->first()
        );
    }

    /**
     * Returns an error response when the widget is missing or not allowed on
     * the calling host, otherwise null.
     */
    protected function refuse(Request $request, ?Widget $widget): ?JsonResponse
    {
        if (! $widget) {
            return $this->embedJson(['error' => 'widget_not_found'], 404);
        }

        $host = $this->refHost($request);

        if (! $widget->allowsHost($host)) {
            return $this->embedJson([
                'error'   => 'domain_not_allowed',
                'message' => "This widget is not authorised for {$host}.",
            ], 403);
        }

        return null;
    }

    protected function refHost(Request $request): ?string
    {
        $origin = $request->header('Origin') ?: $request->header('Referer');

        return $origin ? parse_url($origin, PHP_URL_HOST) : null;
    }

    protected function embedJson(array $body, int $status = 200, ?string $cacheControl = null): JsonResponse
    {
        return response()->json($body, $status)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Cache-Control', $cacheControl ?? 'no-store');
    }
}
