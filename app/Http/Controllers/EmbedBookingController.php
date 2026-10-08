<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesEmbedWidget;
use App\Models\Booking;
use App\Models\Widget;
use App\Services\Booking\BookingException;
use App\Services\Booking\BookingService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

/**
 * Guest checkout from inside the widget.
 *
 * Every POST body arrives as text/plain JSON so cross-origin calls stay
 * "simple requests" with no preflight (same rule as the tracking beacon).
 * The booking's access token travels in the body or query — never a cookie.
 */
class EmbedBookingController extends Controller
{
    use ResolvesEmbedWidget;

    public function __construct(private BookingService $bookings)
    {
    }

    /** POST /api/embed/{publicKey}/bookings — hold seats, open a PaymentIntent. */
    public function store(Request $request, string $publicKey): JsonResponse
    {
        $widget = $this->resolveWidget($publicKey);

        if ($refusal = $this->refuse($request, $widget)) {
            return $refusal;
        }

        if (! in_array($widget->type, config('weyfarin.booking.widget_types'), true)) {
            return $this->embedJson(['error' => 'booking_not_enabled', 'message' => 'This widget does not take bookings.'], 403);
        }

        $in = $this->body($request);

        $slug        = is_string($in['experience'] ?? null) ? $in['experience'] : '';
        $departureId = (int) ($in['departure_id'] ?? 0);
        $adults      = (int) ($in['adults'] ?? 0);
        $children    = (int) ($in['children'] ?? 0);
        $key         = is_string($in['idempotency_key'] ?? null) ? $in['idempotency_key'] : '';
        $max         = (int) config('weyfarin.booking.max_guests_per_booking');

        if ($slug === '' || $departureId < 1 || $adults < 1 || $children < 0 || $adults + $children > $max
            || ! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $key)) {
            return $this->embedJson(['error' => 'invalid_request', 'message' => 'Please check your selection and try again.'], 422);
        }

        // Scope check (PLAN.md D18): only experiences this widget exposes.
        $experience = $widget->findExperience($slug);
        if (! $experience) {
            return $this->embedJson(['error' => 'experience_not_found', 'message' => 'This experience is not available.'], 404);
        }

        if ($children > 0 && ! $experience->childrenAllowed()) {
            return $this->embedJson(['error' => 'children_not_allowed', 'message' => "This experience is for ages {$experience->min_age} and up."], 422);
        }

        if ($experience->group_size_max && $adults + $children > $experience->group_size_max) {
            return $this->embedJson(['error' => 'group_too_large', 'message' => "The maximum group size is {$experience->group_size_max}."], 422);
        }

        $departure = $experience->departures()->whereKey($departureId)->upcoming()->first();
        if (! $departure) {
            return $this->embedJson(['error' => 'departure_unavailable', 'message' => 'This date is no longer available.'], 422);
        }

        try {
            $result = $this->bookings->create($widget, $experience, $departure, $adults, $children, $key, $this->refHost($request));
        } catch (BookingException $e) {
            return $this->embedJson(['error' => $e->errorCode, 'message' => $e->getMessage()] + $e->extra, $e->status);
        } catch (ApiErrorException $e) {
            Log::error('Stripe PaymentIntent create failed', ['error' => $e->getMessage()]);

            return $this->embedJson(['error' => 'payment_provider_error', 'message' => 'Payment could not be started. Please try again.'], 502);
        }

        $booking = $result['booking'];

        return $this->embedJson([
            'reference'       => $booking->reference,
            'token'           => $result['token'],
            'client_secret'   => $result['client_secret'],
            'publishable_key' => config('stripe.key'),
            'hold_seconds'    => $booking->holdSecondsLeft(),
        ] + $this->summary($booking), 201);
    }

    /** POST /api/embed/{publicKey}/bookings/{reference}/guest */
    public function guest(Request $request, string $publicKey, string $reference): JsonResponse
    {
        [$booking, $refusal] = $this->ownedBooking($request, $publicKey, $reference);
        if ($refusal) {
            return $refusal;
        }

        $in    = $this->body($request);
        $name  = trim((string) ($in['name'] ?? ''));
        $email = trim((string) ($in['email'] ?? ''));
        $phone = trim((string) ($in['phone'] ?? ''));

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120)                  $errors['name']  = 'Enter your full name.';
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) $errors['email'] = 'Enter a valid email address.';
        if (! preg_match('/^\+?[0-9 ()-]{7,20}$/', $phone))                  $errors['phone'] = 'Enter a valid phone number.';

        if ($errors) {
            return $this->embedJson(['error' => 'invalid_guest', 'message' => 'Please check your details.', 'fields' => $errors], 422);
        }

        try {
            $this->bookings->attachGuest($booking, $name, $email, $phone);
        } catch (BookingException $e) {
            return $this->embedJson(['error' => $e->errorCode, 'message' => $e->getMessage()], $e->status);
        }

        return $this->embedJson(['ok' => true]);
    }

    /** POST /api/embed/{publicKey}/bookings/{reference}/release — guest left checkout. */
    public function release(Request $request, string $publicKey, string $reference): JsonResponse
    {
        [$booking, $refusal] = $this->ownedBooking($request, $publicKey, $reference);
        if ($refusal) {
            return $refusal;
        }

        $this->bookings->release($booking, 'released');

        return $this->embedJson(['ok' => true]);
    }

    /** GET /api/embed/{publicKey}/bookings/{reference}?token= — poll after payment. */
    public function show(Request $request, string $publicKey, string $reference): JsonResponse
    {
        [$booking, $refusal] = $this->ownedBooking($request, $publicKey, $reference);
        if ($refusal) {
            return $refusal;
        }

        $booking = $this->bookings->reconcile($booking);

        return $this->embedJson([
            'reference'    => $booking->reference,
            'status'       => $booking->status,
            'hold_seconds' => $booking->holdSecondsLeft(),
            'guest_name'   => $booking->guest_name,
            'guest_email'  => $booking->guest_email,
        ] + $this->summary($booking));
    }

    // ------------------------------------------------------------------

    /** @return array{0: ?Booking, 1: ?JsonResponse} */
    private function ownedBooking(Request $request, string $publicKey, string $reference): array
    {
        $widget = $this->resolveWidget($publicKey);

        if ($refusal = $this->refuse($request, $widget)) {
            return [null, $refusal];
        }

        $token = $request->isMethod('get')
            ? (string) $request->query('token', '')
            : (string) ($this->body($request)['token'] ?? '');

        $booking = Booking::with(['payment', 'experience.media', 'departure'])
            ->where('reference', $reference)
            ->where('widget_id', $widget->id)
            ->first();

        // Same 404 for "no such booking" and "wrong token" — a reference
        // guesser learns nothing.
        if (! $booking || ! $booking->tokenMatches($token)) {
            return [null, $this->embedJson(['error' => 'booking_not_found', 'message' => 'Booking not found.'], 404)];
        }

        return [$booking, null];
    }

    /** Server-computed figures — the widget displays these, never its own. */
    private function summary(Booking $booking): array
    {
        $currency   = strtoupper($booking->currency);
        $experience = $booking->experience;
        $departure  = $booking->departure;

        $lines = [[
            'label'  => $booking->adults . ' ' . ($booking->adults === 1 ? 'adult' : 'adults'),
            'unit'   => Money::toMajor($booking->adult_unit_minor, $currency),
            'qty'    => $booking->adults,
            'amount' => Money::toMajor($booking->adults * $booking->adult_unit_minor, $currency),
        ]];

        if ($booking->children > 0) {
            $lines[] = [
                'label'  => $booking->children . ' ' . ($booking->children === 1 ? 'child' : 'children'),
                'unit'   => Money::toMajor($booking->child_unit_minor, $currency),
                'qty'    => $booking->children,
                'amount' => Money::toMajor($booking->children * $booking->child_unit_minor, $currency),
            ];
        }

        return [
            'currency'   => $currency,
            'total'      => Money::toMajor($booking->gross_minor, $currency),
            'lines'      => $lines,
            'experience' => [
                'title' => $experience?->title,
                'image' => $experience?->coverImageUrl(800, 600),
            ],
            'date_label' => $departure
                ? $departure->date->format('D, j M Y') . ($departure->start_time ? ', ' . $departure->start_time : '')
                : null,
        ];
    }

    private function body(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }
}