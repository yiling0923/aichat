<?php

namespace App\Reports;

use App\Bot\FlowRepository;
use App\Models\AiUsage;
use App\Models\Booking;
use App\Models\BotEvent;
use App\Models\Broadcast;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\Survey;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every number on the Reports page and in its CSV files, for one period and a few filters.
 * Everything is limited to the signed-in business by the BusinessScope.
 */
class ReportData
{
    public const PERIODS = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'this_month' => 'This month', 'last_month' => 'Last month', 'custom' => 'Custom dates'];

    public const CHANNELS = ['web' => 'Website', 'whatsapp' => 'WhatsApp', 'messenger' => 'Messenger', 'instagram' => 'Instagram'];

    public Carbon $from;

    public Carbon $to;

    public string $period;

    public string $label;

    public ?string $channel = null;

    public ?int $serviceId = null;

    public ?int $practitionerId = null;

    private string $tz;

    public function __construct(private Business $business, Request $request, private FlowRepository $flows)
    {
        $this->tz = $business->timezone ?: 'UTC';
        $now = Carbon::now($this->tz);

        $period = (string) ($request->query('period') ?? $request->query('days', '30'));
        $this->period = array_key_exists($period, self::PERIODS) ? $period : '30';

        [$this->from, $this->to, $this->label] = match (true) {
            $this->period === 'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'This month'],
            $this->period === 'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), 'Last month'],
            $this->period === 'custom' => $this->custom($request, $now),
            default => [$now->copy()->subDays((int) $this->period - 1)->startOfDay(), $now->copy()->endOfDay(), 'The last '.(int) $this->period.' days'],
        };

        $channel = (string) $request->query('channel', '');
        $this->channel = array_key_exists($channel, self::CHANNELS) ? $channel : null;
        $this->serviceId = $request->integer('service') ?: null;
        $this->practitionerId = $request->integer('practitioner') ?: null;
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function custom(Request $request, Carbon $now): array
    {
        try {
            $from = Carbon::parse((string) $request->query('from'), $this->tz)->startOfDay();
            $to = Carbon::parse((string) $request->query('to'), $this->tz)->endOfDay();
        } catch (\Throwable) {
            $from = $now->copy()->subDays(29)->startOfDay();
            $to = $now->copy()->endOfDay();
        }

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366)->startOfDay();
        }

        return [$from, $to, $from->format('j M Y').' to '.$to->format('j M Y')];
    }

    public function filtered(): bool
    {
        return $this->channel !== null || $this->serviceId !== null || $this->practitionerId !== null;
    }

    public function timezone(): string
    {
        return $this->tz;
    }

    /** Chats started in a period (all channels), for the funnel. */
    public function chatCount(CarbonInterface $from, CarbonInterface $to): int
    {
        return Conversation::query()->where('channel', '!=', 'preview')->whereBetween('created_at', [$from, $to])->count();
    }

    /** The period right before this one, same length. @return array{0: Carbon, 1: Carbon} */
    public function previous(): array
    {
        $days = $this->from->diffInDays($this->to) + 1;

        return [$this->from->copy()->subDays($days), $this->from->copy()->subSecond()];
    }

    private function chats(CarbonInterface $from, CarbonInterface $to)
    {
        return Conversation::query()->where('channel', '!=', 'preview')->whereBetween('created_at', [$from, $to])
            ->when($this->channel, fn ($q) => $q->where('channel', $this->channel));
    }

    private function bookings(CarbonInterface $from, CarbonInterface $to)
    {
        return Booking::query()->whereBetween('created_at', [$from, $to])
            ->when($this->serviceId, fn ($q) => $q->where('service_id', $this->serviceId))
            ->when($this->practitionerId, fn ($q) => $q->where('practitioner_id', $this->practitionerId))
            ->when($this->channel, fn ($q) => $q->whereIn('conversation_id', Conversation::query()->where('channel', $this->channel)->select('id')));
    }

    private function count(string $what, CarbonInterface $from, CarbonInterface $to): float|int
    {
        return match ($what) {
            'chats' => $this->chats($from, $to)->count(),
            'customers' => Customer::query()->whereBetween('created_at', [$from, $to])->count(),
            'bookings' => $this->bookings($from, $to)->count(),
            'handovers' => BotEvent::query()->where('type', 'handover')->whereBetween('created_at', [$from, $to])->count(),
            'revenue' => (float) Order::query()->where('status', 'paid')->whereBetween('ordered_at', [$from, $to])->sum('amount'),
        };
    }

    /** All numbers for the page. @return array<string, mixed> */
    public function summary(): array
    {
        [$pf, $pt] = $this->previous();
        $kpi = [];
        foreach (['chats', 'customers', 'bookings', 'handovers', 'revenue'] as $k) {
            $now = $this->count($k, $this->from, $this->to);
            $before = $this->count($k, $pf, $pt);
            $kpi[$k] = ['value' => $now, 'delta' => $before > 0 ? (int) round(($now - $before) / $before * 100) : null, 'before' => $before];
        }

        $bookingRows = $this->bookings($this->from, $this->to)->get(['id', 'service_id', 'practitioner_id', 'status', 'starts_at', 'customer_id']);
        $byStatus = $bookingRows->countBy('status');
        $decided = ($byStatus['completed'] ?? 0) + ($byStatus['no_show'] ?? 0);

        $surveys = Survey::query()->whereNotNull('rating')->whereBetween('answered_at', [$this->from, $this->to]);

        $orders = Order::query()->whereBetween('ordered_at', [$this->from, $this->to]);

        $labels = [];
        foreach ($this->flows->published($this->business)->nodes() as $node) {
            foreach ((array) ($node['options'] ?? []) as $o) {
                $labels[$o['id'] ?? ''] = $o['label']['en'] ?? ($o['id'] ?? '');
            }
        }

        $services = \App\Models\Service::query()->get()->keyBy('id');
        $practitioners = \App\Models\Practitioner::query()->get()->keyBy('id');
        $valid = $bookingRows->whereIn('status', ['confirmed', 'completed']);

        return [
            'kpi' => $kpi,
            'chatsByChannel' => $this->chats($this->from, $this->to)->select('channel', DB::raw('count(*) as n'))->groupBy('channel')->orderByDesc('n')->get()
                ->map(fn ($r) => ['label' => self::CHANNELS[$r->channel] ?? ucfirst($r->channel), 'n' => (int) $r->n])->all(),
            'leadsByScore' => Customer::query()->whereBetween('created_at', [$this->from, $this->to])->select('score', DB::raw('count(*) as n'))->groupBy('score')->orderByDesc('n')->get()
                ->map(fn ($r) => ['label' => (config('aichat.lead_levels')[$r->score] ?? 'Not set'), 'n' => (int) $r->n])->all(),
            'bookingStatus' => $byStatus->map(fn ($n, $k) => ['label' => ucfirst(str_replace('_', ' ', $k)), 'n' => $n])->values()->all(),
            'topServices' => $valid->countBy('service_id')->sortDesc()->take(6)->map(fn ($n, $id) => ['label' => $services[$id]?->label('en') ?? '-', 'n' => $n])->values()->all(),
            'byPractitioner' => $valid->countBy('practitioner_id')->sortDesc()->take(8)->map(fn ($n, $id) => ['label' => $practitioners[$id]?->name ?? '-', 'n' => $n])->values()->all(),
            'byWeekday' => $this->weekdays($valid),
            'byHour' => $this->hours($valid),
            'topTaps' => BotEvent::query()->where('type', 'option')->whereBetween('created_at', [$this->from, $this->to])
                ->select('option_id', DB::raw('count(*) as n'))->groupBy('option_id')->orderByDesc('n')->limit(8)->get()
                ->map(fn ($e) => ['label' => $labels[$e->option_id] ?? $e->option_id, 'n' => (int) $e->n])->all(),
            'ratings' => collect([5, 4, 3, 2, 1])->map(fn ($r) => ['label' => $r.' star'.($r > 1 ? 's' : ''), 'n' => (clone $surveys)->where('rating', $r)->count()])->all(),
            'surveyAvg' => (clone $surveys)->avg('rating'),
            'surveyCount' => (clone $surveys)->count(),
            'avgWait' => $this->averageWait(),
            'noShowRate' => $decided > 0 ? (int) round(($byStatus['no_show'] ?? 0) / $decided * 100) : null,
            'cancelRate' => $bookingRows->count() > 0 ? (int) round(($byStatus['cancelled'] ?? 0) / $bookingRows->count() * 100) : null,
            'repeatCustomers' => $valid->pluck('customer_id')->filter()->unique()->filter(fn ($id) => Booking::query()->where('customer_id', $id)->whereIn('status', ['confirmed', 'completed'])->count() > 1)->count(),
            'ordersByStatus' => (clone $orders)->select('status', DB::raw('count(*) as n'), DB::raw('sum(amount) as total'))->groupBy('status')->get()
                ->map(fn ($r) => ['label' => ucfirst($r->status), 'n' => (int) $r->n, 'total' => (float) $r->total])->all(),
            'orderCount' => (clone $orders)->count(),
            'optedOut' => Customer::query()->whereNotNull('opted_out_at')->whereBetween('opted_out_at', [$this->from, $this->to])->count(),
            'aiCalls' => AiUsage::query()->whereBetween('created_at', [$this->from, $this->to])->count(),
            'broadcasts' => Broadcast::query()->where('status', 'sent')->whereBetween('sent_at', [$this->from, $this->to])->get(),
            'daily' => $this->daily(),
        ];
    }

    /** One row per day: chats, new customers, bookings made, paid sales. @return list<array{date: string, chats: int, customers: int, bookings: int, revenue: float}> */
    public function daily(): array
    {
        $days = [];
        for ($d = $this->from->copy(); $d->lte($this->to); $d->addDay()) {
            $days[$d->format('Y-m-d')] = ['date' => $d->format('Y-m-d'), 'chats' => 0, 'customers' => 0, 'bookings' => 0, 'revenue' => 0.0];
        }

        $bucket = function (Collection $times, string $key) use (&$days): void {
            foreach ($times as $t) {
                $day = Carbon::parse($t, 'UTC')->setTimezone($this->tz)->format('Y-m-d');
                if (isset($days[$day])) {
                    $days[$day][$key]++;
                }
            }
        };

        $bucket($this->chats($this->from, $this->to)->limit(20000)->pluck('created_at'), 'chats');
        $bucket(Customer::query()->whereBetween('created_at', [$this->from, $this->to])->limit(20000)->pluck('created_at'), 'customers');
        $bucket($this->bookings($this->from, $this->to)->limit(20000)->pluck('created_at'), 'bookings');

        foreach (Order::query()->where('status', 'paid')->whereBetween('ordered_at', [$this->from, $this->to])->limit(20000)->get(['ordered_at', 'amount']) as $o) {
            $day = Carbon::parse($o->ordered_at, 'UTC')->setTimezone($this->tz)->format('Y-m-d');
            if (isset($days[$day])) {
                $days[$day]['revenue'] += (float) $o->amount;
            }
        }

        return array_values($days);
    }

    private function weekdays(Collection $bookings): array
    {
        $names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $n = array_fill(0, 7, 0);
        foreach ($bookings as $b) {
            $n[$b->starts_at->copy()->setTimezone($this->tz)->dayOfWeekIso - 1]++;
        }

        return collect($names)->map(fn ($l, $i) => ['label' => $l, 'n' => $n[$i]])->all();
    }

    private function hours(Collection $bookings): array
    {
        $n = [];
        foreach ($bookings as $b) {
            $h = $b->starts_at->copy()->setTimezone($this->tz)->hour;
            $n[$h] = ($n[$h] ?? 0) + 1;
        }
        ksort($n);

        return collect($n)->map(fn ($c, $h) => ['label' => sprintf('%02d:00', $h), 'n' => $c])->values()->all();
    }

    private function averageWait(): ?int
    {
        $waits = [];
        $handed = Conversation::query()->where('channel', '!=', 'preview')->whereNotNull('handover_at')->whereBetween('handover_at', [$this->from, $this->to])
            ->when($this->channel, fn ($q) => $q->where('channel', $this->channel))->limit(2000)->get(['id', 'handover_at']);

        foreach ($handed as $c) {
            $first = Message::query()->where('conversation_id', $c->id)->where('sender_type', 'staff')->where('is_private', false)
                ->where('created_at', '>=', $c->handover_at)->orderBy('id')->value('created_at');
            if ($first !== null) {
                $waits[] = $c->handover_at->diffInMinutes(Carbon::parse($first));
            }
        }

        return $waits === [] ? null : (int) round(array_sum($waits) / count($waits));
    }

    // ---- CSV (each returns a header row and data rows) ----

    public const EXPORTS = [
        'summary' => 'Summary numbers',
        'daily' => 'Day by day',
        'bookings' => 'Bookings (detail)',
        'customers' => 'New customers (detail)',
        'orders' => 'Orders (detail)',
        'surveys' => 'Survey answers (detail)',
    ];

    /** CSV files offered next to the Sales and Marketing tabs (built in SalesMarketing). */
    public const DETAIL_EXPORTS = [
        'sales_summary' => 'Sales numbers', 'sales_channel' => 'Money by channel', 'sales_service' => 'Money by service', 'sales_person' => 'Money by practitioner', 'sales_customers' => 'Best customers', 'sales_funnel' => 'First message to payment',
        'mk_channels' => 'Where customers come from', 'mk_topics' => 'What customers ask about', 'mk_broadcasts' => 'Broadcast results', 'mk_automations' => 'Automatic messages', 'mk_winback' => 'Customers to win back',
    ];

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    public function export(string $section): array
    {
        $local = fn ($t) => $t ? Carbon::parse($t)->setTimezone($this->tz)->format('Y-m-d H:i') : '';

        return match ($section) {
            'daily' => [['Date', 'Chats started', 'New customers', 'Bookings made'], array_map(fn ($r) => [$r['date'], $r['chats'], $r['customers'], $r['bookings']], $this->daily())],
            'bookings' => [['Booked on', 'Visit', 'Customer', 'Phone', 'Service', 'Practitioner', 'Status'],
                $this->bookings($this->from, $this->to)->with(['customer', 'service', 'practitioner'])->orderBy('starts_at')->limit(20000)->get()
                    ->map(fn ($b) => [$local($b->created_at), $local($b->starts_at), $b->customer?->name, $b->customer?->phone, $b->service?->label('en'), $b->practitioner?->name, $b->status])->all()],
            'customers' => [['Added', 'Name', 'Phone', 'Email', 'Language', 'Lead level', 'Interest', 'Opted out of promotions'],
                Customer::query()->whereBetween('created_at', [$this->from, $this->to])->orderBy('created_at')->limit(20000)->get()
                    ->map(fn ($c) => [$local($c->created_at), $c->name, $c->phone, $c->email, $c->language, config('aichat.lead_levels')[$c->score] ?? '', $c->interest, $c->opted_out_at ? 'yes' : 'no'])->all()],
            'orders' => [['Date', 'Reference', 'Customer', 'Items', 'Amount', 'Status'],
                Order::query()->with('customer')->whereBetween('ordered_at', [$this->from, $this->to])->orderBy('ordered_at')->limit(20000)->get()
                    ->map(fn ($o) => [$local($o->ordered_at), $o->reference, $o->customer?->name, $o->items, number_format((float) $o->amount, 2, '.', ''), $o->status])->all()],
            'surveys' => [['Answered', 'Customer', 'Rating', 'Comment'],
                Survey::query()->with('customer')->whereNotNull('rating')->whereBetween('answered_at', [$this->from, $this->to])->orderBy('answered_at')->limit(20000)->get()
                    ->map(fn ($s) => [$local($s->answered_at), $s->customer?->name, $s->rating, $s->comment])->all()],
            default => $this->summaryRows(),
        };
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    private function summaryRows(): array
    {
        $s = $this->summary();
        $rows = [
            ['Period', $this->label], ['Channel filter', $this->channel ? self::CHANNELS[$this->channel] : 'All'],
            ['Chats started', $s['kpi']['chats']['value']], ['New customers', $s['kpi']['customers']['value']],
            ['Bookings made', $s['kpi']['bookings']['value']], ['Handed over to staff', $s['kpi']['handovers']['value']],
            ['Paid orders (amount)', number_format($s['kpi']['revenue']['value'], 2, '.', '')], ['Orders (all statuses)', $s['orderCount']],
            ['Average wait for staff reply (min)', $s['avgWait']], ['Survey average (of 5)', $s['surveyAvg'] === null ? '' : number_format($s['surveyAvg'], 2)],
            ['Survey answers', $s['surveyCount']], ['No-show rate %', $s['noShowRate']], ['Cancellation rate %', $s['cancelRate']],
            ['Customers who booked more than once', $s['repeatCustomers']], ['Opted out of promotions', $s['optedOut']], ['AI calls', $s['aiCalls']],
        ];
        foreach (['chatsByChannel' => 'Chats by channel', 'bookingStatus' => 'Bookings by status', 'topServices' => 'Top services', 'byPractitioner' => 'Bookings by practitioner', 'topTaps' => 'Most tapped buttons'] as $key => $title) {
            foreach ($s[$key] as $r) {
                $rows[] = [$title.': '.$r['label'], $r['n']];
            }
        }

        return [['Measure', 'Value'], $rows];
    }
}
