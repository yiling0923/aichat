<?php

namespace App\Reports;

use App\Marketing\AutomationDefaults;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Booking;
use App\Models\Broadcast;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\Survey;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The detailed Sales and Marketing numbers for the period chosen on the Reports page.
 * Money is what customers paid (orders marked paid). Everything is limited to the signed-in business.
 */
class SalesMarketing
{
    /** Customers who have not visited for this many days count as "to win back". */
    public const WINBACK_DAYS = 60;

    /** An unpaid order older than this many days is shown as overdue. */
    public const OVERDUE_DAYS = 7;

    private string $tz;

    public function __construct(private ReportData $r)
    {
        $this->tz = $r->timezone();
    }

    private function paid(Carbon $from, Carbon $to)
    {
        return $this->inChannel(Order::query()->where('status', 'paid')->whereBetween('ordered_at', [$from, $to]));
    }

    /** @var Collection<int, string>|null customer id => the channel they first wrote from */
    private ?Collection $origins = null;

    /** Money is credited to the channel the customer first wrote from (website, WhatsApp, Messenger ...). */
    private function origins(): Collection
    {
        return $this->origins ??= Conversation::query()->where('channel', '!=', 'preview')->whereNotNull('customer_id')->orderBy('id')->get(['customer_id', 'channel'])->unique('customer_id')->pluck('channel', 'customer_id');
    }

    /** Limits an orders query to customers who came from the channel chosen on the Reports page (all of them when none is chosen). */
    private function inChannel($query)
    {
        if ($this->r->channel === null) {
            return $query;
        }

        return $query->whereIn('customer_id', $this->origins()->filter(fn ($c) => $c === $this->r->channel)->keys());
    }

    /** Limits a bookings query to chats on the chosen channel. */
    private function bookingsInChannel($query)
    {
        return $this->r->channel === null ? $query : $query->whereIn('conversation_id', Conversation::query()->where('channel', $this->r->channel)->select('id'));
    }

    /** @param  Collection<int, Order>  $orders @return array<string, float> money per channel label, orders with no chat grouped apart */
    private function moneyByChannel(Collection $orders): array
    {
        $totals = [];
        foreach ($orders as $o) {
            $channel = $o->customer_id !== null ? $this->origins()->get($o->customer_id) : null;
            $label = $channel !== null ? (ReportData::CHANNELS[$channel] ?? ucfirst((string) $channel)) : 'No chat (typed in by hand)';
            $totals[$label] = ($totals[$label] ?? 0) + (float) $o->amount;
        }
        arsort($totals);

        return $totals;
    }

    private function delta(float $now, float $before): ?int
    {
        return $before > 0 ? (int) round(($now - $before) / $before * 100) : null;
    }

    /** @return array<string, mixed> */
    public function sales(): array
    {
        [$pf, $pt] = $this->r->previous();
        $from = $this->r->from;
        $to = $this->r->to;

        $orders = $this->paid($from, $to)->get(['id', 'customer_id', 'reference', 'items', 'amount', 'ordered_at']);
        $revenue = (float) $orders->sum('amount');
        $count = $orders->count();
        $beforeRevenue = (float) $this->paid($pf, $pt)->sum('amount');
        $beforeCount = $this->paid($pf, $pt)->count();
        $aov = $count > 0 ? $revenue / $count : 0.0;
        $beforeAov = $beforeCount > 0 ? $beforeRevenue / $beforeCount : 0.0;

        // Which service and person earned it: visits create orders called BK-<booking id>.
        $bookingIds = $orders->map(fn ($o) => preg_match('/^BK-(\d+)$/', (string) $o->reference, $m) ? (int) $m[1] : null)->filter()->unique()->values();
        $bookings = Booking::query()->whereIn('id', $bookingIds)->get(['id', 'service_id', 'practitioner_id'])->keyBy('id');
        $services = \App\Models\Service::query()->get()->keyBy('id');
        $people = \App\Models\Practitioner::query()->get()->keyBy('id');
        $byService = [];
        $byPerson = [];
        foreach ($orders as $o) {
            $b = preg_match('/^BK-(\d+)$/', (string) $o->reference, $m) ? ($bookings[(int) $m[1]] ?? null) : null;
            $sk = $b ? ($services[$b->service_id]?->label('en') ?? '-') : 'Other orders (typed in by hand)';
            $pk = $b ? ($people[$b->practitioner_id]?->name ?? '-') : 'Other orders (typed in by hand)';
            $byService[$sk] = ($byService[$sk] ?? 0) + (float) $o->amount;
            $byPerson[$pk] = ($byPerson[$pk] ?? 0) + (float) $o->amount;
        }

        // Weeks and weekdays: when does the money arrive?
        $weeks = [];
        $weekdays = array_fill(0, 7, 0.0);
        foreach ($orders as $o) {
            $d = Carbon::parse($o->ordered_at, 'UTC')->setTimezone($this->tz);
            $weeks[$d->copy()->startOfWeek()->format('Y-m-d')] = ($weeks[$d->copy()->startOfWeek()->format('Y-m-d')] ?? 0) + (float) $o->amount;
            $weekdays[$d->dayOfWeekIso - 1] += (float) $o->amount;
        }
        ksort($weeks);

        // Best customers.
        $names = Customer::query()->whereIn('id', $orders->pluck('customer_id')->filter()->unique())->get(['id', 'name', 'phone'])->keyBy('id');
        $top = $orders->groupBy('customer_id')->map(fn (Collection $g, $id) => [
            'id' => (int) $id, 'name' => $names[$id]->name ?? ($names[$id]->phone ?? __('No customer')),
            'orders' => $g->count(), 'spent' => (float) $g->sum('amount'), 'last' => $g->max('ordered_at'),
        ])->sortByDesc('spent')->take(10)->values()->all();

        // New or returning: did they pay us before this period?
        $ids = $orders->pluck('customer_id')->filter()->unique();
        $returning = $ids->isEmpty() ? collect() : Order::query()->where('status', 'paid')->whereIn('customer_id', $ids)->where('ordered_at', '<', $from)->pluck('customer_id')->unique();
        $returningRevenue = (float) $orders->filter(fn ($o) => $returning->contains($o->customer_id))->sum('amount');

        // Unpaid and refunded.
        $pending = $this->inChannel(Order::query()->where('status', 'pending')->whereBetween('ordered_at', [$from, $to]));
        $overdueQuery = $this->inChannel(Order::query()->where('status', 'pending')->where('ordered_at', '<', now()->subDays(self::OVERDUE_DAYS)));
        $overdue = (clone $overdueQuery)->with('customer')->orderBy('ordered_at')->limit(10)->get()
            ->map(fn ($o) => ['id' => $o->id, 'customer' => $o->customer?->name ?: ($o->customer?->phone ?: '-'), 'items' => $o->items, 'amount' => (float) $o->amount, 'days' => (int) $o->ordered_at->diffInDays(now())])->all();

        // Booked ahead, and what no-shows and cancellations cost.
        $ahead = $this->bookingsInChannel(Booking::query()->with('service')->where('status', 'confirmed')->whereBetween('starts_at', [now(), now()->addDays(30)]))->limit(2000)->get();
        $lost = $this->bookingsInChannel(Booking::query()->with('service')->whereIn('status', ['no_show', 'cancelled'])->whereBetween('starts_at', [$from, $to]))->limit(2000)->get();

        // From first message to payment.
        $chats = $this->r->channel === null ? $this->r->chatCount($from, $to) : Conversation::query()->where('channel', $this->r->channel)->whereBetween('created_at', [$from, $to])->count();
        $leads = Customer::query()->whereBetween('created_at', [$from, $to])->when($this->r->channel !== null, fn ($q) => $q->whereIn('id', $this->origins()->filter(fn ($c) => $c === $this->r->channel)->keys()))->count();
        $made = $this->bookingsInChannel(Booking::query()->whereBetween('created_at', [$from, $to])->where('status', '!=', 'cancelled'))->count();
        $visited = $this->bookingsInChannel(Booking::query()->whereBetween('starts_at', [$from, $to])->where('status', 'completed'))->count();
        $funnel = [['label' => 'Chats started', 'n' => $chats], ['label' => 'Customers who left their details', 'n' => $leads], ['label' => 'Bookings made', 'n' => $made], ['label' => 'Visits completed', 'n' => $visited], ['label' => 'Paid orders', 'n' => $count]];

        return [
            'revenue' => $revenue, 'revenueDelta' => $this->delta($revenue, $beforeRevenue),
            'orders' => $count, 'ordersDelta' => $this->delta($count, $beforeCount),
            'aov' => $aov, 'aovDelta' => $this->delta($aov, $beforeAov),
            'pendingAmount' => (float) (clone $pending)->sum('amount'), 'pendingCount' => (clone $pending)->count(),
            'refunded' => (float) $this->inChannel(Order::query()->where('status', 'refunded')->whereBetween('ordered_at', [$from, $to]))->sum('amount'),
            'overdueAmount' => (float) (clone $overdueQuery)->sum('amount'), 'overdueCount' => (clone $overdueQuery)->count(), 'overdue' => $overdue,
            'byService' => $this->rows($byService), 'byPerson' => $this->rows($byPerson), 'byChannel' => $this->rows($this->moneyByChannel($orders)),
            'byWeek' => collect($weeks)->map(fn ($v, $k) => ['label' => Carbon::parse($k)->format('j M'), 'n' => round($v, 2)])->values()->all(),
            'byWeekday' => collect(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])->map(fn ($l, $i) => ['label' => $l, 'n' => round($weekdays[$i], 2)])->all(),
            'topCustomers' => $top,
            'newRevenue' => $revenue - $returningRevenue, 'returningRevenue' => $returningRevenue,
            'bookedAhead' => (float) $ahead->sum(fn ($b) => (float) ($b->service->price ?? 0)), 'bookedAheadCount' => $ahead->count(),
            'lostValue' => (float) $lost->sum(fn ($b) => (float) ($b->service->price ?? 0)), 'lostCount' => $lost->count(),
            'funnel' => $funnel,
        ];
    }

    /** @param array<string, float> $totals @return list<array{label: string, n: float}> */
    private function rows(array $totals): array
    {
        arsort($totals);

        return collect($totals)->take(10)->map(fn ($v, $k) => ['label' => $k, 'n' => round($v, 2)])->values()->all();
    }

    /** @return array<string, mixed> */
    public function marketing(): array
    {
        $from = $this->r->from;
        $to = $this->r->to;
        $chats = Conversation::query()->where('channel', '!=', 'preview')->whereBetween('created_at', [$from, $to])->get(['id', 'channel', 'customer_id', 'created_at']);

        // Where customers come from, and how many of those chats end in a booking.
        $bookingsByChat = Booking::query()->whereBetween('created_at', [$from, $to])->where('status', '!=', 'cancelled')->whereNotNull('conversation_id')->pluck('conversation_id')->unique();
        $messages = Message::query()->join('conversations', 'conversations.id', '=', 'messages.conversation_id')->where('conversations.channel', '!=', 'preview')
            ->where('messages.direction', 'in')->whereBetween('messages.created_at', [$from, $to])
            ->select('conversations.channel', DB::raw('count(*) as n'))->groupBy('conversations.channel')->pluck('n', 'channel');
        $paidAll = Order::query()->where('status', 'paid')->whereBetween('ordered_at', [$from, $to])->get(['customer_id', 'amount']);
        $channelMoney = [];
        foreach ($paidAll as $o) {
            $ch = $o->customer_id !== null ? $this->origins()->get($o->customer_id) : null;
            if ($ch !== null) {
                $channelMoney[$ch] = ($channelMoney[$ch] ?? 0) + (float) $o->amount;
            }
        }
        $channels = [];
        foreach ($chats->when($this->r->channel !== null, fn ($c) => $c->where('channel', $this->r->channel))->groupBy('channel') as $channel => $group) {
            $n = $group->count();
            $booked = $group->filter(fn ($c) => $bookingsByChat->contains($c->id))->count();
            $channels[] = [
                'label' => ReportData::CHANNELS[$channel] ?? ucfirst((string) $channel), 'chats' => $n, 'messages' => (int) ($messages[$channel] ?? 0), 'money' => round($channelMoney[$channel] ?? 0, 2),
                'leads' => $group->pluck('customer_id')->filter()->unique()->count(), 'bookings' => $booked,
                'rate' => $n > 0 ? (int) round($booked / $n * 100) : 0,
            ];
        }
        usort($channels, fn ($a, $b) => $b['chats'] <=> $a['chats']);

        // What customers ask about (the automatic chat tags).
        $topics = Tag::query()->where('type', 'chat')->withCount(['conversations' => fn ($q) => $q->where('channel', '!=', 'preview')->whereBetween('conversations.created_at', [$from, $to])])->get()
            ->filter(fn ($t) => $t->conversations_count > 0)->sortByDesc('conversations_count')->take(10)->map(fn ($t) => ['label' => $t->name, 'n' => (int) $t->conversations_count])->values()->all();

        // When customers write.
        $byWeekday = array_fill(0, 7, 0);
        $byHour = array_fill(0, 24, 0);
        foreach ($chats as $c) {
            $d = $c->created_at->copy()->setTimezone($this->tz);
            $byWeekday[$d->dayOfWeekIso - 1]++;
            $byHour[$d->hour]++;
        }
        arsort($byHour);

        // The list you can message, and how it moves.
        $contactable = Customer::query()->whereNotNull('consented_at')->whereNull('opted_out_at')->count();
        $languages = Customer::query()->select('language', DB::raw('count(*) as n'))->groupBy('language')->orderByDesc('n')->get()
            ->map(fn ($r) => ['label' => $r->language === 'zh' ? '中文' : ($r->language === 'en' ? 'English' : ($r->language ?: 'Not set')), 'n' => (int) $r->n])->all();
        $customerTags = Tag::query()->where('type', 'customer')->withCount('customers')->get()->filter(fn ($t) => $t->customers_count > 0)->sortByDesc('customers_count')->take(8)
            ->map(fn ($t) => ['label' => $t->name, 'n' => (int) $t->customers_count])->values()->all();

        // Did broadcasts bring bookings and money? (people who booked or paid within 7 days of the message)
        $broadcasts = Broadcast::query()->where('status', 'sent')->whereBetween('sent_at', [$from, $to])->orderByDesc('sent_at')->limit(15)->get()->map(function ($b) {
            $ids = DB::table('broadcast_recipients')->where('broadcast_id', $b->id)->where('status', 'sent')->pluck('customer_id')->filter();
            $until = $b->sent_at->copy()->addDays(7);
            $booked = $ids->isEmpty() ? 0 : Booking::query()->whereIn('customer_id', $ids)->whereBetween('created_at', [$b->sent_at, $until])->where('status', '!=', 'cancelled')->distinct('customer_id')->count('customer_id');
            $money = $ids->isEmpty() ? 0.0 : (float) Order::query()->where('status', 'paid')->whereIn('customer_id', $ids)->whereBetween('ordered_at', [$b->sent_at, $until])->sum('amount');
            $total = $b->sent_count + $b->skipped_count + $b->failed_count;

            return ['id' => $b->id, 'name' => $b->name, 'sent_at' => $b->sent_at, 'sent' => $b->sent_count, 'skipped' => $b->skipped_count, 'failed' => $b->failed_count,
                'delivery' => $total > 0 ? (int) round($b->sent_count / $total * 100) : 0, 'booked' => $booked, 'revenue' => $money];
        })->all();

        // Automatic messages.
        $titles = collect(AutomationDefaults::all())->map(fn ($d) => $d['title'] ?? '')->all();
        $logs = AutomationLog::query()->whereBetween('created_at', [$from, $to])->select('automation_key', 'status', DB::raw('count(*) as n'))->groupBy('automation_key', 'status')->get()->groupBy('automation_key');
        $automations = $logs->map(fn ($g, $key) => [
            'label' => $titles[$key] ?? ucfirst(str_replace('_', ' ', (string) $key)),
            'sent' => (int) ($g->firstWhere('status', 'sent')->n ?? 0), 'skipped' => (int) ($g->firstWhere('status', 'skipped')->n ?? 0), 'failed' => (int) ($g->firstWhere('status', 'failed')->n ?? 0),
        ])->sortByDesc('sent')->values()->all();

        // Satisfaction and reviews.
        $asked = Survey::query()->whereNotNull('sent_at')->whereBetween('sent_at', [$from, $to])->count();
        $answered = Survey::query()->whereNotNull('rating')->whereBetween('answered_at', [$from, $to]);
        $ratings = (clone $answered)->pluck('rating');
        $happy = $ratings->filter(fn ($r) => $r >= 4)->count();
        $unhappy = $ratings->filter(fn ($r) => $r <= 2)->count();

        return [
            'channels' => $channels, 'topics' => $topics,
            'weekdays' => collect(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])->map(fn ($l, $i) => ['label' => $l, 'n' => $byWeekday[$i]])->all(),
            'busyHours' => collect($byHour)->take(6)->filter(fn ($n) => $n > 0)->map(fn ($n, $h) => ['label' => sprintf('%02d:00', $h), 'n' => $n])->values()->all(),
            'contactable' => $contactable, 'customersTotal' => Customer::query()->count(),
            'newOptIns' => Customer::query()->whereNotNull('consented_at')->whereBetween('consented_at', [$from, $to])->count(),
            'optOuts' => Customer::query()->whereNotNull('opted_out_at')->whereBetween('opted_out_at', [$from, $to])->count(),
            'languages' => $languages, 'customerTags' => $customerTags,
            'broadcasts' => $broadcasts, 'automations' => $automations,
            'surveyAsked' => $asked, 'surveyAnswered' => $ratings->count(), 'surveyRate' => $asked > 0 ? (int) round($ratings->count() / $asked * 100) : null,
            'happy' => $happy, 'unhappy' => $unhappy, 'avgRating' => $ratings->isEmpty() ? null : round($ratings->avg(), 1),
            'reviewLinks' => Survey::query()->whereNotNull('review_sent_at')->whereBetween('review_sent_at', [$from, $to])->count(),
            'winBack' => $this->winBack(),
        ];
    }

    /** Customers who used to come but have not for a while (and may be messaged). @return array{count: int, list: list<array<string, mixed>>} */
    public function winBack(int $limit = 10): array
    {
        $cut = now()->subDays(self::WINBACK_DAYS);
        $last = Booking::query()->whereIn('status', ['confirmed', 'completed'])->whereNotNull('customer_id')
            ->select('customer_id', DB::raw('max(starts_at) as last_visit'), DB::raw('count(*) as visits'))->groupBy('customer_id')->get();

        $upcoming = Booking::query()->where('status', 'confirmed')->where('starts_at', '>', now())->pluck('customer_id')->unique();
        $lapsed = $last->filter(fn ($r) => Carbon::parse($r->last_visit, 'UTC')->lt($cut) && ! $upcoming->contains($r->customer_id));
        $customers = Customer::query()->whereIn('id', $lapsed->pluck('customer_id'))->whereNull('opted_out_at')->get()->keyBy('id');
        $spent = Order::query()->where('status', 'paid')->whereIn('customer_id', $customers->keys())->select('customer_id', DB::raw('sum(amount) as total'))->groupBy('customer_id')->pluck('total', 'customer_id');

        $rows = $lapsed->filter(fn ($r) => $customers->has($r->customer_id))->map(function ($r) use ($customers, $spent) {
            $c = $customers[$r->customer_id];

            return ['id' => $c->id, 'name' => $c->name ?: ($c->phone ?: '-'), 'phone' => $c->phone, 'last' => Carbon::parse($r->last_visit, 'UTC')->setTimezone($this->tz), 'visits' => (int) $r->visits,
                'spent' => (float) ($spent[$c->id] ?? 0), 'reachable' => $c->canBeMessaged()];
        })->sortByDesc('spent')->values();

        return ['count' => $rows->count(), 'reachable' => $rows->where('reachable', true)->count(), 'list' => $rows->take($limit)->all()];
    }

    /** CSV exports for this tab. @return array{0: list<string>, 1: list<list<mixed>>}|null */
    public function export(string $section): ?array
    {
        return match ($section) {
            'sales_summary' => $this->salesExport(),
            'sales_channel' => [['Channel', 'Paid (money)'], array_map(fn ($r) => [$r['label'], number_format($r['n'], 2, '.', '')], $this->sales()['byChannel'])],
            'sales_service' => [['Service', 'Paid (money)'], array_map(fn ($r) => [$r['label'], number_format($r['n'], 2, '.', '')], $this->sales()['byService'])],
            'sales_person' => [['Practitioner', 'Paid (money)'], array_map(fn ($r) => [$r['label'], number_format($r['n'], 2, '.', '')], $this->sales()['byPerson'])],
            'sales_customers' => [['Customer', 'Orders', 'Spent', 'Last order'], array_map(fn ($r) => [$r['name'], $r['orders'], number_format($r['spent'], 2, '.', ''), $r['last']], $this->sales()['topCustomers'])],
            'sales_funnel' => [['Step', 'Count'], array_map(fn ($r) => [$r['label'], $r['n']], $this->sales()['funnel'])],
            'mk_channels' => [['Channel', 'Chats', 'Messages from customers', 'Left details', 'Bookings', 'Chat to booking %', 'Paid (money)'], array_map(fn ($r) => [$r['label'], $r['chats'], $r['messages'], $r['leads'], $r['bookings'], $r['rate'], number_format($r['money'], 2, '.', '')], $this->marketing()['channels'])],
            'mk_topics' => [['Topic customers asked about', 'Chats'], array_map(fn ($r) => [$r['label'], $r['n']], $this->marketing()['topics'])],
            'mk_broadcasts' => [['Broadcast', 'Sent on', 'Delivered', 'Skipped', 'Failed', 'Booked within 7 days', 'Paid within 7 days'], array_map(fn ($r) => [$r['name'], $r['sent_at'], $r['sent'], $r['skipped'], $r['failed'], $r['booked'], number_format($r['revenue'], 2, '.', '')], $this->marketing()['broadcasts'])],
            'mk_automations' => [['Automatic message', 'Sent', 'Skipped', 'Failed'], array_map(fn ($r) => [$r['label'], $r['sent'], $r['skipped'], $r['failed']], $this->marketing()['automations'])],
            'mk_winback' => [['Customer', 'Phone', 'Last visit', 'Visits', 'Spent', 'May be messaged'], array_map(fn ($r) => [$r['name'], $r['phone'], $r['last']->format('Y-m-d'), $r['visits'], number_format($r['spent'], 2, '.', ''), $r['reachable'] ? 'yes' : 'no'], $this->winBack(5000)['list'])],
            default => null,
        };
    }

    private function salesExport(): array
    {
        $s = $this->sales();

        return [['Measure', 'Value'], [
            ['Paid orders (money)', number_format($s['revenue'], 2, '.', '')], ['Paid orders (count)', $s['orders']], ['Average order', number_format($s['aov'], 2, '.', '')],
            ['Waiting for payment (this period)', number_format($s['pendingAmount'], 2, '.', '')], ['Overdue unpaid (older than '.self::OVERDUE_DAYS.' days)', number_format($s['overdueAmount'], 2, '.', '')],
            ['Refunded', number_format($s['refunded'], 2, '.', '')], ['From new customers', number_format($s['newRevenue'], 2, '.', '')], ['From returning customers', number_format($s['returningRevenue'], 2, '.', '')],
            ['Booked ahead, next 30 days (value)', number_format($s['bookedAhead'], 2, '.', '')], ['Lost to no-shows and cancellations (value)', number_format($s['lostValue'], 2, '.', '')],
        ]];
    }
}
