<?php

namespace App\Http\Controllers;

use App\Bot\FlowRepository;
use App\Models\Booking;
use App\Models\BotEvent;
use App\Models\Broadcast;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\Survey;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the owner: chats, customers, bookings, handovers, reply time, top questions, surveys, orders.
 */
class ReportsController extends Controller
{
    public function index(Request $request, FlowRepository $flows): View
    {
        $days = in_array((int) $request->query('days', 30), [7, 30, 90], true) ? (int) $request->query('days', 30) : 30;
        $since = now()->subDays($days);
        $business = $request->user()->business;

        $chats = Conversation::query()->where('channel', '!=', 'preview')->where('created_at', '>=', $since);

        $byChannel = (clone $chats)->select('channel', DB::raw('count(*) as n'))->groupBy('channel')->orderByDesc('n')->pluck('n', 'channel');
        $leadsByScore = Customer::query()->where('created_at', '>=', $since)->select('score', DB::raw('count(*) as n'))->groupBy('score')->pluck('n', 'score');
        $bookingsByStatus = Booking::query()->where('created_at', '>=', $since)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        $topServices = Booking::query()->with('service')->where('created_at', '>=', $since)->whereIn('status', ['confirmed', 'completed'])
            ->select('service_id', DB::raw('count(*) as n'))->groupBy('service_id')->orderByDesc('n')->limit(5)->get()
            ->map(fn ($b) => ['label' => $b->service?->label('en') ?? '-', 'n' => (int) $b->n]);

        // Minutes from "handed over" to the first staff reply, for chats handed over in this period.
        $handed = Conversation::query()->where('channel', '!=', 'preview')->whereNotNull('handover_at')->where('handover_at', '>=', $since)->get(['id', 'handover_at']);
        $waits = [];
        foreach ($handed as $c) {
            $first = Message::query()->where('conversation_id', $c->id)->where('sender_type', 'staff')->where('is_private', false)
                ->where('created_at', '>=', $c->handover_at)->orderBy('id')->value('created_at');
            if ($first !== null) {
                $waits[] = $c->handover_at->diffInMinutes(\Carbon\Carbon::parse($first));
            }
        }

        // Most tapped buttons, with their English names from the published menu.
        $labels = [];
        foreach ($flows->published($business)->nodes() as $node) {
            foreach ((array) ($node['options'] ?? []) as $o) {
                $labels[$o['id'] ?? ''] = $o['label']['en'] ?? ($o['id'] ?? '');
            }
        }
        $topTaps = BotEvent::query()->where('type', 'option')->where('created_at', '>=', $since)
            ->select('option_id', DB::raw('count(*) as n'))->groupBy('option_id')->orderByDesc('n')->limit(8)->get()
            ->map(fn ($e) => ['label' => $labels[$e->option_id] ?? $e->option_id, 'n' => (int) $e->n]);

        $surveys = Survey::query()->whereNotNull('rating')->where('answered_at', '>=', $since);

        return view('admin.reports', [
            'business' => $business,
            'active' => 'reports',
            'days' => $days,
            'chatsTotal' => (clone $chats)->count(),
            'byChannel' => $byChannel,
            'newCustomers' => Customer::query()->where('created_at', '>=', $since)->count(),
            'leadsByScore' => $leadsByScore,
            'bookingsByStatus' => $bookingsByStatus,
            'topServices' => $topServices,
            'handovers' => BotEvent::query()->where('type', 'handover')->where('created_at', '>=', $since)->count(),
            'avgWait' => $waits === [] ? null : (int) round(array_sum($waits) / count($waits)),
            'topTaps' => $topTaps,
            'surveyCount' => (clone $surveys)->count(),
            'surveyAvg' => (clone $surveys)->avg('rating'),
            'revenue' => Order::query()->where('status', 'paid')->where('ordered_at', '>=', $since)->sum('amount'),
            'broadcasts' => Broadcast::query()->where('status', 'sent')->where('sent_at', '>=', $since)->get(),
        ]);
    }
}
