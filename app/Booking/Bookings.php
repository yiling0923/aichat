<?php

namespace App\Booking;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Practitioner;
use App\Models\Service;
use App\Services\LeadService;
use App\Services\N8nNotifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Saves and cancels bookings. The slot is checked again inside a transaction
 * with the practitioner locked, so two people cannot take the same time.
 */
class Bookings
{
    public function __construct(
        private Availability $availability,
        private GoogleCalendar $google,
        private LeadService $leads,
        private N8nNotifier $n8n,
    ) {}

    /**
     * @param  array<string, mixed>  $details  name, phone collected by the bot
     *
     * @throws SlotTakenException when the time is no longer free
     */
    public function book(Conversation $conversation, Service $service, Practitioner $practitioner, CarbonInterface $start, array $details): Booking
    {
        $business = $conversation->business;
        $start = $start->copy()->utc();

        $booking = DB::transaction(function () use ($conversation, $service, $practitioner, $start, $business): Booking {
            Practitioner::query()->whereKey($practitioner->id)->lockForUpdate()->first();

            if (! $this->availability->isFree($business, $service, $practitioner, $start)) {
                throw new SlotTakenException('That time was just taken.');
            }

            $booking = new Booking([
                'conversation_id' => $conversation->id,
                'service_id' => $service->id,
                'practitioner_id' => $practitioner->id,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes($service->duration_min),
                'status' => 'confirmed',
            ]);
            $booking->save();

            return $booking;
        });

        $customer = $this->leads->capture($conversation, array_merge($details, ['interest' => $service->label($conversation->language)]), 'hot', false);
        $booking->forceFill(['customer_id' => $customer->id])->save();

        if ($practitioner->google_calendar_id && $this->google->enabled()) {
            try {
                $eventId = $this->google->createEvent(
                    $practitioner->google_calendar_id,
                    $service->label('en').' - '.($customer->name ?: 'Customer'),
                    'Phone: '.($customer->phone ?: '-')."\nBooked through the chat assistant.",
                    $booking->starts_at,
                    $booking->ends_at,
                );
                $booking->forceFill(['google_event_id' => $eventId])->save();
            } catch (Throwable $e) {
                // The booking is saved and blocks the slot here; staff see it in the Schedule page.
                Log::warning('Google event not created', ['booking' => $booking->id, 'error' => $e->getMessage()]);
            }
        }

        $this->n8n->send('booking.created', $this->payload($booking, $customer));

        return $booking;
    }

    public function cancel(Booking $booking, string $by = 'customer'): void
    {
        if ($booking->status !== 'confirmed') {
            return;
        }

        $booking->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

        if ($booking->google_event_id && $booking->practitioner->google_calendar_id && $this->google->enabled()) {
            try {
                $this->google->deleteEvent($booking->practitioner->google_calendar_id, $booking->google_event_id);
            } catch (Throwable $e) {
                Log::warning('Google event not deleted', ['booking' => $booking->id, 'error' => $e->getMessage()]);
            }
        }

        $this->n8n->send('booking.cancelled', $this->payload($booking, $booking->customer) + ['cancelled_by' => $by]);
    }

    /**
     * Staff move a booking to another time (same practitioner and service). Other bookings of that practitioner
     * are checked again inside a transaction; Google Calendar events that staff added by hand are not.
     *
     * @return array{notified: bool}
     *
     * @throws SlotTakenException when the practitioner already has a booking at that time
     */
    public function reschedule(Booking $booking, CarbonInterface $start, bool $notify = false): array
    {
        $start = $start->copy()->utc();
        $end = $start->copy()->addMinutes($booking->service->duration_min);
        $buffer = BookingRules::for($booking->business)['buffer_min'];

        DB::transaction(function () use ($booking, $start, $end, $buffer): void {
            Practitioner::query()->whereKey($booking->practitioner_id)->lockForUpdate()->first();

            $clash = Booking::query()->where('practitioner_id', $booking->practitioner_id)->where('status', 'confirmed')->whereKeyNot($booking->id)
                ->where('starts_at', '<', $end->copy()->addMinutes($buffer))->where('ends_at', '>', $start->copy()->subMinutes($buffer))->exists();

            if ($clash) {
                throw new SlotTakenException($booking->practitioner->name.' already has a booking around that time.');
            }

            $booking->forceFill(['starts_at' => $start, 'ends_at' => $end, 'reminder_24_sent_at' => null, 'reminder_2_sent_at' => null])->save();
        });

        $calendar = $booking->practitioner->google_calendar_id;
        if ($booking->google_event_id && $calendar && $this->google->enabled()) {
            try {
                $this->google->deleteEvent($calendar, $booking->google_event_id);
                $eventId = $this->google->createEvent($calendar, $booking->service->label('en').' - '.($booking->customer?->name ?: 'Customer'),
                    'Phone: '.($booking->customer?->phone ?: '-')."\nBooked through the chat assistant.", $booking->starts_at, $booking->ends_at);
                $booking->forceFill(['google_event_id' => $eventId])->save();
            } catch (Throwable $e) {
                $booking->forceFill(['google_event_id' => null])->save();
                Log::warning('Google event not moved', ['booking' => $booking->id, 'error' => $e->getMessage()]);
            }
        }

        $notified = false;
        $chat = $booking->conversation;
        if ($notify && $chat !== null && $chat->windowOpen()) {
            $language = $chat->language ?? $booking->customer?->language ?? 'en';
            $chat->addMessage('out', 'bot', \App\Bot\Text::fill(\App\Bot\Strings::get('booking_rescheduled', $language), [
                'service' => $booking->service->label($language),
                'when' => app(\App\Bot\BookingStep::class)->whenLabel($booking, $language),
            ]));
            $notified = true;
        }

        $this->n8n->send('booking.rescheduled', $this->payload($booking, $booking->customer) + ['notified_customer' => $notified]);

        return ['notified' => $notified];
    }

    /** Customers may cancel until cancel_hours before the start. */
    public function customerMayCancel(Booking $booking): bool
    {
        return $booking->starts_at->gt(now()->addHours(BookingRules::for($booking->business)['cancel_hours']));
    }

    /** @return array<string, mixed> */
    private function payload(Booking $booking, ?Customer $customer): array
    {
        $business = $booking->business;

        return [
            'business' => $business->slug,
            'booking_id' => $booking->id,
            'conversation_id' => $booking->conversation_id,
            'service' => $booking->service->label('en'),
            'practitioner' => $booking->practitioner->name,
            'starts_at' => $booking->starts_at->copy()->setTimezone($business->timezone ?: 'UTC')->toIso8601String(),
            'customer_name' => $customer?->name,
            'customer_phone' => $customer?->phone,
        ];
    }
}
