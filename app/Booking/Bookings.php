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
