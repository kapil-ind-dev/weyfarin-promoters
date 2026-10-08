<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Day 1 gate — Stripe Elements slot-pattern spike.
 *
 * Local environment and test keys only. Delete this file and routes/spike.php
 * once the gate decision is recorded in PLAN.md.
 */
class StripeSpikeController extends Controller
{
    /**
     * Amounts live on the server. The browser sends a currency, never a number.
     * Same rule the real booking endpoint will follow.
     */
    private const AMOUNTS = [
        'inr' => 300381,   // ₹3,003.81 — the Figma checkout total
        'sgd' => 4900,
        'usd' => 3600,
    ];

    /** GET /api/spike/config?currency=inr */
    public function config(Request $request): JsonResponse
    {
        if ($refusal = $this->refuse()) {
            return $refusal;
        }

        $currency = $this->currency($request->query('currency'));

        return $this->json([
            'publishable_key' => config('stripe.key'),
            'amount'          => self::AMOUNTS[$currency],
            'currency'        => $currency,
        ]);
    }

    /**
     * POST /api/spike/intents
     *
     * Body arrives as text/plain JSON so the cross-origin request stays
     * "simple" and never preflights — same convention as the tracking beacon.
     */
    public function createIntent(Request $request): JsonResponse
    {
        if ($refusal = $this->refuse()) {
            return $refusal;
        }

        $payload  = json_decode($request->getContent(), true);
        $payload  = is_array($payload) ? $payload : [];
        $currency = $this->currency($payload['currency'] ?? null);

        $idempotency = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($payload['idempotency_key'] ?? ''));
        $idempotency = $idempotency !== '' ? Str::limit($idempotency, 64, '') : (string) Str::uuid();

        try {
            $intent = $this->stripe()->paymentIntents->create([
                'amount'               => self::AMOUNTS[$currency],
                'currency'             => $currency,
                // payment_method_types is rejected by current API versions —
                // methods come from Dashboard → Settings → Payment methods.
                // allow_redirects=never keeps the user on the partner page:
                // redirect-based methods (bank redirects, some wallets) are
                // excluded, so confirmPayment never navigates away.
                'automatic_payment_methods' => [
                    'enabled'         => true,
                    'allow_redirects' => 'never',
                ],
                'description'          => 'Weyfarin Day 1 slot-pattern spike',
                'metadata'             => [
                    'spike'       => 'day1',
                    'source_host' => (string) parse_url((string) $request->header('Origin'), PHP_URL_HOST),
                ],
            ], [
                'idempotency_key' => 'spike-' . $idempotency,
            ]);
        } catch (ApiErrorException $e) {
            return $this->json(['message' => $e->getMessage()], 422);
        }

        return $this->json([
            'id'            => $intent->id,
            'client_secret' => $intent->client_secret,
        ]);
    }

    /**
     * GET /api/spike/intents/{id}
     *
     * The browser's "succeeded" is a claim. This is the check. In the real
     * flow the webhook does this job; the spike just proves the round trip.
     */
    public function showIntent(string $id): JsonResponse
    {
        if ($refusal = $this->refuse()) {
            return $refusal;
        }

        try {
            $intent = $this->stripe()->paymentIntents->retrieve($id, ['expand' => ['latest_charge']]);
        } catch (ApiErrorException $e) {
            return $this->json(['message' => $e->getMessage()], 404);
        }

        $data = $intent->toArray();

        return $this->json([
            'id'             => $intent->id,
            'status'         => $intent->status,
            'amount'         => $intent->amount,
            'currency'       => $intent->currency,
            'card_brand'     => data_get($data, 'latest_charge.payment_method_details.card.brand'),
            'last4'          => data_get($data, 'latest_charge.payment_method_details.card.last4'),
            'three_d_secure' => data_get($data, 'latest_charge.payment_method_details.card.three_d_secure.result'),
            'error'          => data_get($data, 'last_payment_error.message'),
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * Returns a JSON refusal (with CORS headers, so the browser shows the
     * message instead of an opaque CORS error), or null when it is safe to run.
     */
    private function refuse(): ?JsonResponse
    {
        if (! app()->environment('local')) {
            return $this->json(['message' => 'Spike endpoints run in the local environment only.'], 404);
        }

        $key    = (string) config('stripe.key');
        $secret = (string) config('stripe.secret');

        if ($key === '' || $secret === '') {
            return $this->json(['message' => 'STRIPE_KEY / STRIPE_SECRET missing from .env — run php artisan config:clear after adding them.'], 500);
        }

        if (! str_starts_with($key, 'pk_test_') || ! str_starts_with($secret, 'sk_test_')) {
            return $this->json(['message' => 'Refusing to run the spike with live keys. Use pk_test_ / sk_test_.'], 500);
        }

        return null;
    }

    private function currency(mixed $value): string
    {
        $value = strtolower((string) $value);

        return array_key_exists($value, self::AMOUNTS) ? $value : 'inr';
    }

    private function stripe(): StripeClient
    {
        return new StripeClient((string) config('stripe.secret'));
    }

    private function json(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Cache-Control', 'no-store');
    }
}
