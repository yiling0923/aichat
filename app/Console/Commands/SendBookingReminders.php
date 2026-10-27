<?php

namespace App\Console\Commands;

use App\Bot\BookingStep;
use App\Bot\Strings;
use App\Bot\Text;
use App\Models\Booking;
use App\Channels\Templates;
use App\Models\Business;
use App\Models\MessageTemplate;
use App\Services\N8nNotifier;
use App\Support\CurrentBusiness;
use Illuminate\Console\Command;

/**
 * Runs every minute. Sends each booking a reminder 24 hours and 2 hours before the visit
 * (only when the booking was made earlier than that). The reminder goes into the chat and to n8n,
 * which can also send it as a WhatsApp template when the 24-hour chat window has closed.
 */
class SendBookingReminders extends Command
{
    protected $signature = 'aichat:send-reminders';

    protected $description = 'Send booking reminders 24 hours and 2 hours before the visit';

    private const STEPS = [['hours' => 24, 'column' => 'reminder_24_sent_at'], ['hours' => 2, 'column' => 'reminder_2_sent_at']];

    public function handle(CurrentBusiness $current, N8nNotifier $n8n, BookingStep $steps): int
    {
        $sent = 0;

        foreach (Business::query()->where('status', 'active')->get() as $business) {
            $current->runAs($business, function () use ($business, $n8n, $steps, &$sent) {
                foreach (self::STEPS as $step) {
                    Booking::query()
                        ->where('status', 'confirmed')
                        ->whereNull($step['column'])
                        ->where('starts_at', '>', now())
                        ->where('starts_at', '<=', now()->addHours($step['hours']))
                        ->with(['service', 'practitioner', 'customer', 'conversation'])
                        ->each(function (Booking $b) use ($business, $n8n, $steps, $step, &$sent) {
                            $b->forceFill([$step['column'] => now()])->save();

                            // Booked inside the window already: the confirmation was the reminder.
                            if ($b->created_at->gt($b->starts_at->copy()->subHours($step['hours']))) {
                                return;
                            }

                            $language = $b->conversation?->language ?? $b->customer?->language ?? 'en';

                            $chat = $b->conversation;
                            $when = $steps->whenLabel($b, $language);

                            if ($chat !== null && $chat->windowOpen()) {
                                $chat->addMessage('out', 'bot', Text::fill(Strings::get('booking_reminder', $language), [
                                    'service' => $b->service->label($language),
                                    'when' => $when,
                                ]));
                            } elseif ($chat !== null) {
                                // The 24-hour window is closed: WhatsApp only allows an approved template.
                                $name = $chat->channelAccount?->settings['reminder_template'] ?? null;
                                $template = $name ? MessageTemplate::query()->where('channel_id', $chat->channel_id)->where('name', $name)->where('status', 'APPROVED')->first() : null;

                                if ($template !== null) {
                                    try {
                                        app(Templates::class)->send($chat, $template, [(string) ($b->customer?->name ?? ''), $b->service->label($language), $when], 'bot');
                                    } catch (\Throwable $e) {
                                        report($e);
                                    }
                                }
                            }

                            $n8n->send('booking.reminder', [
                                'business' => $business->slug,
                                'booking_id' => $b->id,
                                'hours_before' => $step['hours'],
                                'service' => $b->service->label('en'),
                                'starts_at' => $b->starts_at->copy()->setTimezone($business->timezone ?: 'UTC')->toIso8601String(),
                                'customer_name' => $b->customer?->name,
                                'customer_phone' => $b->customer?->phone,
                                'language' => $language,
                                'window_open' => $chat?->windowOpen() ?? true,
                            ]);
                            $sent++;
                        });
                }
            });
        }

        $this->info($sent.' reminder(s).');

        return self::SUCCESS;
    }
}
