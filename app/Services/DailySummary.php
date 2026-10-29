<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\Task;
use App\Support\CurrentBusiness;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** The numbers for one day of one business, in the business's own time zone. No AI: plain counting. */
class DailySummary
{
    /** @return array<string, mixed> */
    public function forDay(Business $business, CarbonInterface $day): array
    {
        return app(CurrentBusiness::class)->runAs($business, function () use ($business, $day) {
            $tz = $business->timezone ?: 'UTC';
            $from = $day->copy()->setTimezone($tz)->startOfDay()->utc();
            $to = $day->copy()->setTimezone($tz)->endOfDay()->utc();
            $real = fn ($q) => $q->where('channel', '!=', 'preview');

            $chatIds = Message::query()->where('direction', 'in')->where('sender_type', 'customer')->where('is_private', false)
                ->whereBetween('created_at', [$from, $to])->distinct()->pluck('conversation_id');

            $topics = $chatIds->isEmpty() ? [] : DB::table('conversation_tag')->join('tags', 'tags.id', '=', 'conversation_tag.tag_id')
                ->whereIn('conversation_tag.conversation_id', $chatIds)->where('tags.business_id', $business->id)
                ->groupBy('tags.name')->orderByDesc(DB::raw('count(*)'))->limit(5)->get(['tags.name', DB::raw('count(*) as n')])
                ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->n])->all();

            $paid = Order::query()->where('status', 'paid')->whereBetween('ordered_at', [$from, $to]);

            return [
                'label' => $day->copy()->setTimezone($tz)->translatedFormat(app()->getLocale() === 'zh' ? 'n月j日 l' : 'l, j M'),
                'new_chats' => $real(Conversation::query())->whereBetween('created_at', [$from, $to])->count(),
                'chats_with_messages' => $chatIds->count(),
                'bookings_made' => Booking::query()->whereBetween('created_at', [$from, $to])->count(),
                'visits' => Booking::query()->whereIn('status', ['confirmed', 'completed'])->whereBetween('starts_at', [$from, $to])->count(),
                'cancelled' => Booking::query()->where('status', 'cancelled')->whereBetween('updated_at', [$from, $to])->count(),
                'new_customers' => Customer::query()->whereBetween('created_at', [$from, $to])->count(),
                'orders_paid' => (clone $paid)->count(),
                'revenue' => (float) (clone $paid)->sum('amount'),
                'topics' => $topics,
                'waiting_now' => $real(Conversation::query())->where('status', 'open')->where('mode', 'human')->count(),
                'overdue_tasks' => Task::query()->whereNull('done_at')->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'tomorrow' => Booking::query()->where('status', 'confirmed')->whereBetween('starts_at', [$from->copy()->addDay(), $to->copy()->addDay()])->count(),
                'currency' => $business->settings['currency'] ?? 'RM',
            ];
        });
    }
}
