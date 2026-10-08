<?php

namespace App\Http\Controllers;

use App\Services\Booking\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * POST /stripe/webhook — the source of truth for "paid".
 *
 * Local testing:
 *   stripe listen --forward-to localhost:8000/stripe/webhook
 * and put the printed whsec_… in STRIPE_WEBHOOK_SECRET.
 *
 * Without the CLI the widget's status poll reconciles against Stripe
 * directly, so the demo still completes — the webhook is what makes it
 * correct when the guest closes the tab right after paying.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, BookingService $bookings): Response
    {
        $secret = (string) config('stripe.webhook_secret');

        if ($secret === '') {
            Log::warning('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not set.');

            return response('Webhook secret not configured', 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret
            );
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            return response('Invalid payload or signature', 400);
        }

        $intent = $event->data->object;

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $bookings->confirmFromIntent($intent->id, (int) $intent->amount_received, (string) $intent->currency);
                break;

            case 'payment_intent.payment_failed':
                $bookings->recordFailure($intent->id, $intent->last_payment_error?->message);
                break;
        }

        // 2xx for everything we verified, including types we ignore —
        // otherwise Stripe retries them for three days.
        return response()->noContent();
    }
}