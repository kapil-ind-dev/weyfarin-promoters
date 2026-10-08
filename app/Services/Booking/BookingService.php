<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingSplit;
use App\Models\Experience;
use App\Models\ExperienceDeparture;
use App\Models\Payment;
use App\Models\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use Throwable;

/**
 * Seat holds, PaymentIntents, confirmation and splits.
 *
 * Concurrency rule: every seat movement is a single conditional UPDATE
 * ("… WHERE seats_left >= n"), and every status change is a conditional
 * UPDATE on the current status. Whoever's UPDATE affects a row wins; the
 * other caller sees 0 rows and backs off. No read-then-write races, no
 * reliance on row locks — identical on SQLite and MySQL.
 *
 * The webhook and the widget's status poll both call confirmFromIntent();
 * the conditional status update makes the second call a no-op.
 */
class BookingService
{
    public function __construct(private BookingPricer $pricer)
    {
    }

    // ------------------------------------------------------------- create

    /**
     * Hold seats and open a PaymentIntent.
     *
     * @return array{booking: Booking, token: ?string, client_secret: string}
     */
    public function create(
        Widget $widget,
        Experience $experience,
        ExperienceDeparture $departure,
        int $adults,
        int $children,
        string $idempotencyKey,
        ?string $sourceHost,
    ): array {
        // Same click retried (double-submit, flaky network): hand back the
        // same booking. The token is not stored in plain text, so a replay
        // gets a fresh one rotated in.
        $existing = Booking::where('widget_id', $widget->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing && $existing->status === 'pending') {
            return $this->resume($existing);
        }

        // Free seats from abandoned checkouts on this departure before
        // deciding it is sold out — no scheduler needed for correctness.
        $this->expireDue($departure->id);

        $guests = $adults + $children;
        $quote  = $this->pricer->quote($experience, $departure, $adults, $children);
        $token  = Str::random(40);

        $booking = DB::transaction(function () use ($widget, $experience, $departure, $adults, $children, $guests, $quote, $token, $idempotencyKey, $sourceHost) {
            $held = ExperienceDeparture::whereKey($departure->id)
                ->where('status', 'open')
                ->whereRaw('seats_total - seats_held - seats_sold >= ?', [$guests])
                ->increment('seats_held', $guests);

            if (! $held) {
                $left = (int) optional(ExperienceDeparture::find($departure->id))->seatsLeft();

                throw new BookingException(
                    'not_enough_seats',
                    $left > 0
                        ? "Only {$left} " . ($left === 1 ? 'seat is' : 'seats are') . ' left on this date.'
                        : 'This date has just sold out.',
                    409,
                    ['seats_left' => $left],
                );
            }

            return Booking::create([
                'reference'             => $this->newReference(),
                'access_token_hash'     => hash('sha256', $token),
                'widget_id'             => $widget->id,
                'partner_id'            => $widget->partner_id,
                'experience_id'         => $experience->id,
                'departure_id'          => $departure->id,
                'adults'                => $adults,
                'children'              => $children,
                'adult_unit_minor'      => $quote['adult_unit_minor'],
                'child_unit_minor'      => $quote['child_unit_minor'],
                'gross_minor'           => $quote['gross_minor'],
                'currency'              => $quote['currency'],
                'provider_share_bp'     => (int) config('weyfarin.splits.provider_share_bp'),
                'partner_commission_bp' => $this->partnerCommissionBp($widget),
                'status'                => 'pending',
                'hold_expires_at'       => now()->addMinutes((int) config('weyfarin.booking.hold_minutes')),
                'idempotency_key'       => $idempotencyKey,
                'source_host'           => $sourceHost ? Str::limit($sourceHost, 190, '') : null,
            ]);
        });

        // Network call outside the transaction: never hold a DB transaction
        // open while waiting on Stripe.
        try {
            $intent = $this->stripe()->paymentIntents->create([
                'amount'                    => $booking->gross_minor,
                'currency'                  => $booking->currency,
                // payment_method_types is rejected by current API versions.
                // allow_redirects=never keeps the guest on the partner page.
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
                'description'               => 'Weyfarin booking ' . $booking->reference . ' — ' . $experience->title,
                'metadata'                  => [
                    'booking_reference' => $booking->reference,
                    'widget'            => $widget->public_key,
                    'partner_id'        => (string) $widget->partner_id,
                    'experience_id'     => (string) $experience->id,
                    'departure_id'      => (string) $departure->id,
                ],
            ], ['idempotency_key' => 'booking-' . $booking->reference]);
        } catch (Throwable $e) {
            $this->release($booking, 'failed', cancelIntent: false);
            throw $e;
        }

        Payment::create([
            'booking_id'               => $booking->id,
            'stripe_payment_intent_id' => $intent->id,
            'amount_minor'             => $booking->gross_minor,
            'currency'                 => $booking->currency,
            'status'                   => 'requires_payment',
        ]);

        return ['booking' => $booking, 'token' => $token, 'client_secret' => $intent->client_secret];
    }

    /** Idempotent replay of a pending booking: rotate the token, reuse the intent. */
    private function resume(Booking $booking): array
    {
        if (! $booking->payment) {
            throw new BookingException('retry', 'Please try again in a moment.', 409);
        }

        $token = Str::random(40);
        $booking->update(['access_token_hash' => hash('sha256', $token)]);

        $intent = $this->stripe()->paymentIntents->retrieve($booking->payment->stripe_payment_intent_id);

        return ['booking' => $booking, 'token' => $token, 'client_secret' => $intent->client_secret];
    }

    // -------------------------------------------------------------- guest

    public function attachGuest(Booking $booking, string $name, string $email, string $phone): void
    {
        if ($booking->status !== 'pending') {
            throw new BookingException('not_pending', 'This booking can no longer be changed.', 409);
        }

        $booking->update(['guest_name' => $name, 'guest_email' => $email, 'guest_phone' => $phone]);

        // Best effort: nice to have on the Stripe side, never worth failing over.
        try {
            $this->stripe()->paymentIntents->update($booking->payment->stripe_payment_intent_id, [
                'receipt_email' => $email,
                'metadata'      => ['guest_name' => Str::limit($name, 100, '')],
            ]);
        } catch (Throwable $e) {
            Log::warning('Could not update PaymentIntent with guest details', ['booking' => $booking->reference, 'error' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------ release

    /**
     * Give the seats back. Returns false if someone else already moved the
     * booking out of "pending" (it was confirmed, or already released).
     */
    public function release(Booking $booking, string $status = 'released', bool $cancelIntent = true): bool
    {
        $guests = $booking->guests();

        $released = DB::transaction(function () use ($booking, $status, $guests) {
            $won = Booking::whereKey($booking->id)->where('status', 'pending')
                ->update(['status' => $status, 'hold_expires_at' => null]);

            if (! $won) {
                return false;
            }

            ExperienceDeparture::whereKey($booking->departure_id)
                ->where('seats_held', '>=', $guests)
                ->decrement('seats_held', $guests);

            return true;
        });

        if ($released && $cancelIntent && $booking->payment) {
            // Stops a late card submission from charging for seats we just
            // gave away. Fails harmlessly if the intent already succeeded —
            // confirmFromIntent() handles that case.
            try {
                $this->stripe()->paymentIntents->cancel($booking->payment->stripe_payment_intent_id);
            } catch (Throwable $e) {
                // already succeeded / processing / canceled — nothing to do here
            }
        }

        return $released;
    }

    /** Release every pending hold past its expiry. Returns how many. */
    public function expireDue(?int $departureId = null): int
    {
        $due = Booking::query()
            ->where('status', 'pending')
            ->where('hold_expires_at', '<', now())
            ->when($departureId, fn ($q) => $q->where('departure_id', $departureId))
            ->with('payment')
            ->limit(200)
            ->get();

        return $due->filter(fn (Booking $booking) => $this->release($booking, 'expired'))->count();
    }

    // ------------------------------------------------------------ confirm

    /**
     * Called by the webhook and by the status poll. Safe to call any number
     * of times for the same intent.
     */
    public function confirmFromIntent(string $intentId, int $amountReceived, string $currency): ?Booking
    {
        $payment = Payment::with('booking')->where('stripe_payment_intent_id', $intentId)->first();

        if (! $payment || ! $payment->booking) {
            return null;
        }

        $booking = $payment->booking;

        if ($booking->status === 'confirmed') {
            return $booking;
        }

        if ($amountReceived !== (int) $booking->gross_minor || strtolower($currency) !== strtolower($booking->currency)) {
            Log::error('PaymentIntent amount does not match booking', [
                'booking' => $booking->reference, 'expected' => $booking->gross_minor, 'received' => $amountReceived,
            ]);
            $payment->update(['status' => 'succeeded', 'last_error' => 'amount mismatch — manual review']);

            return $booking;
        }

        $guests = $booking->guests();

        DB::transaction(function () use ($booking, $payment, $guests) {
            // Normal path: hold → sold.
            $fromPending = Booking::whereKey($booking->id)->where('status', 'pending')
                ->update(['status' => 'confirmed', 'confirmed_at' => now(), 'hold_expires_at' => null]);

            if ($fromPending) {
                ExperienceDeparture::whereKey($booking->departure_id)->update([
                    'seats_held' => DB::raw("CASE WHEN seats_held >= {$guests} THEN seats_held - {$guests} ELSE 0 END"),
                    'seats_sold' => DB::raw("seats_sold + {$guests}"),
                ]);
            } else {
                // Paid after the hold lapsed. Take the seats again if they are
                // still free; otherwise the money must go back.
                $regained = ExperienceDeparture::whereKey($booking->departure_id)
                    ->whereRaw('seats_total - seats_held - seats_sold >= ?', [$guests])
                    ->increment('seats_sold', $guests);

                $moved = Booking::whereKey($booking->id)->whereIn('status', ['expired', 'released'])
                    ->update($regained
                        ? ['status' => 'confirmed', 'confirmed_at' => now()]
                        : ['status' => 'refund_due']);

                if (! $moved) {
                    return;   // another caller already settled it
                }

                if (! $regained) {
                    Log::warning('Payment succeeded after seats were re-sold — refund due', ['booking' => $booking->reference]);
                    $payment->update(['status' => 'succeeded', 'last_error' => 'seats gone — refund due']);

                    return;
                }
            }

            $payment->update(['status' => 'succeeded', 'last_error' => null]);
            $this->writeSplits($booking);
        });

        return $booking->fresh();
    }

    public function recordFailure(string $intentId, ?string $message): void
    {
        // The booking stays pending: the guest can retry with another card
        // until the hold expires.
        Payment::where('stripe_payment_intent_id', $intentId)
            ->where('status', '!=', 'succeeded')
            ->update(['status' => 'failed', 'last_error' => $message ? Str::limit($message, 250, '') : null]);
    }

    /**
     * Read path for the widget's status poll. If the webhook has not landed
     * yet, ask Stripe directly — makes local demos work without the Stripe
     * CLI, and covers a delayed webhook in production.
     */
    public function reconcile(Booking $booking): Booking
    {
        if (! in_array($booking->status, ['pending', 'expired', 'released'], true) || ! $booking->payment) {
            return $booking;
        }

        try {
            $intent = $this->stripe()->paymentIntents->retrieve($booking->payment->stripe_payment_intent_id);
        } catch (Throwable $e) {
            return $booking;
        }

        if ($intent->status === 'succeeded') {
            return $this->confirmFromIntent($intent->id, (int) $intent->amount_received, $intent->currency) ?? $booking;
        }

        return $booking;
    }

    // ------------------------------------------------------------- splits

    /**
     * Three rows that always sum to gross. Platform takes the rounding
     * remainder so the ledger never drifts by a paisa.
     */
    private function writeSplits(Booking $booking): void
    {
        if ($booking->splits()->exists()) {
            return;
        }

        $gross    = (int) $booking->gross_minor;
        $provider = intdiv($gross * (int) $booking->provider_share_bp, 10000);
        $partner  = $booking->partner_id ? intdiv($gross * (int) $booking->partner_commission_bp, 10000) : 0;
        $platform = $gross - $provider - $partner;

        $rows = [
            ['party_type' => 'provider', 'party_id' => null,                 'amount_minor' => $provider, 'basis_points' => $booking->provider_share_bp],
            ['party_type' => 'partner',  'party_id' => $booking->partner_id, 'amount_minor' => $partner,  'basis_points' => $booking->partner_id ? $booking->partner_commission_bp : 0],
            ['party_type' => 'platform', 'party_id' => null,                 'amount_minor' => $platform, 'basis_points' => null],
        ];

        foreach ($rows as $row) {
            BookingSplit::create($row + ['booking_id' => $booking->id, 'status' => 'accrued']);
        }
    }

    // ------------------------------------------------------------ helpers

    private function partnerCommissionBp(Widget $widget): int
    {
        $raw = $widget->partner?->commission_rate;   // "8%", "8", "8.5%"

        if (is_string($raw) && preg_match('/^\s*(\d+(?:\.\d+)?)\s*%?\s*$/', $raw, $m)) {
            return (int) round(((float) $m[1]) * 100);
        }

        return (int) config('weyfarin.splits.default_partner_commission_bp');
    }

    private function newReference(): string
    {
        $prefix = config('weyfarin.booking.reference_prefix', 'WEY-');

        do {
            $reference = $prefix . random_int(100000, 999999);
        } while (Booking::where('reference', $reference)->exists());

        return $reference;
    }

    private function stripe(): StripeClient
    {
        $secret = (string) config('stripe.secret');

        if ($secret === '') {
            throw new BookingException('payments_unavailable', 'Payments are not configured.', 503);
        }

        return new StripeClient($secret);
    }
}