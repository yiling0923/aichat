<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Survey;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Customers the bot has collected (one per phone number). Limited to the signed-in
 * business by the BusinessScope.
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $score = (string) $request->query('score', '');
        $tag = (int) $request->query('tag', 0);
        $language = (string) $request->query('language', '');
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        $customers = Customer::query()
            ->with('tags')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('interest', 'like', $like)))
            ->when(in_array($score, ['hot', 'warm', 'cold'], true), fn ($q) => $q->where('score', $score))
            ->when(in_array($language, config('aichat.languages'), true), fn ($q) => $q->where('language', $language))
            ->when($tag > 0, fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tag)))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.customers', [
            'business' => $request->user()->business,
            'active' => 'customers',
            'customers' => $customers,
            'tags' => Tag::query()->where('type', 'customer')->orderBy('name')->get(),
            'search' => $search, 'score' => $score, 'tag' => $tag, 'language' => $language,
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $customer = Customer::query()->with(['tags', 'conversations', 'bookings.service', 'bookings.practitioner'])->findOrFail($id);
        $business = $request->user()->business;
        $tz = $business->timezone ?: 'UTC';

        $others = Customer::query()->whereKeyNot($customer->id)->orderBy('name')->limit(400)->get(['id', 'name', 'phone']);
        $orders = Order::query()->where('customer_id', $customer->id)->orderByDesc('ordered_at')->limit(20)->get();
        $surveys = Survey::query()->where('customer_id', $customer->id)->whereNotNull('rating')->orderByDesc('answered_at')->limit(20)->get();
        $bookings = $customer->bookings->sortByDesc('starts_at')->values();
        $upcoming = $bookings->filter(fn ($b) => $b->status === 'confirmed' && $b->starts_at->isFuture())->sortBy('starts_at')->first();
        $lastVisit = $bookings->first(fn ($b) => $b->status === 'completed');

        // One list of everything that happened, newest first.
        $events = collect();
        foreach ($bookings as $b) {
            $events->push(['type' => 'booking', 'at' => $b->starts_at, 'title' => $b->service->label(app()->getLocale()).' ('.__(str_replace('_', ' ', $b->status)).')',
                'sub' => __('with :name', ['name' => $b->practitioner->name]), 'url' => route('schedule', ['view' => 'week', 'date' => $b->starts_at->copy()->setTimezone($tz)->format('Y-m-d')])]);
        }
        foreach ($orders as $o) {
            $events->push(['type' => 'order', 'at' => $o->ordered_at, 'title' => __('Order :amount (:status)', ['amount' => number_format($o->amount, 2), 'status' => __($o->status)]), 'sub' => $o->items, 'url' => route('orders', ['customer' => $customer->id])]);
        }
        foreach ($surveys as $sv) {
            $events->push(['type' => 'survey', 'at' => $sv->answered_at, 'title' => __('Rated the visit :n out of 5', ['n' => $sv->rating]), 'sub' => null, 'url' => null]);
        }
        foreach ($customer->conversations as $c) {
            $events->push(['type' => 'chat', 'at' => $c->last_message_at ?? $c->created_at, 'title' => __(':channel chat', ['channel' => __(\App\Reports\ReportData::CHANNELS[$c->channel] ?? ucfirst($c->channel))]), 'sub' => $c->status === 'open' ? __('open') : __('resolved'), 'url' => route('inbox', ['c' => $c->id])]);
        }
        $events->push(['type' => 'new', 'at' => $customer->created_at, 'title' => __('Became a customer'), 'sub' => $customer->interest ? __('Asked about :x', ['x' => $customer->interest]) : null, 'url' => null]);

        return view('admin.customer', [
            'business' => $business,
            'active' => 'customers',
            'customer' => $customer,
            'others' => $others,
            'allTags' => Tag::query()->where('type', 'customer')->orderBy('name')->get(),
            'tasks' => Task::query()->where('customer_id', $customer->id)->whereNull('done_at')->get(),
            'orders' => $orders,
            'paidTotal' => (float) $orders->where('status', 'paid')->sum('amount'),
            'bookingCount' => $bookings->whereIn('status', ['confirmed', 'completed'])->count(),
            'rating' => $surveys->avg('rating'),
            'upcoming' => $upcoming,
            'lastVisit' => $lastVisit,
            'events' => $events->filter(fn ($e) => $e['at'] !== null)->sortByDesc('at')->take(30)->values(),
            'latestChat' => $customer->conversations->sortByDesc('last_message_at')->first(),
            'tz' => $tz,
            'currency' => $business->settings['currency'] ?? 'RM',
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'language' => ['nullable', Rule::in(config('aichat.languages'))],
            'score' => ['nullable', Rule::in(['hot', 'warm', 'cold'])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'tags' => ['array'],
        ]);

        $customer->update(collect($data)->except('tags')->all());
        $customer->tags()->sync(Tag::query()->where('type', 'customer')->whereIn('id', array_map('intval', $data['tags'] ?? []))->pluck('id'));

        return back()->with('status', __('Customer saved.'));
    }

    /** Everything we hold about one customer, as a file (for a customer who asks for their data). */
    public function export(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $customer = Customer::query()->with(['tags', 'bookings.service'])->findOrFail($id);
        $chats = $customer->conversations()->with(['messages' => fn ($q) => $q->where('is_private', false)->orderBy('id')])->get();

        $data = [
            'customer' => $customer->only(['name', 'phone', 'email', 'language', 'interest', 'details', 'score', 'notes', 'consented_at', 'opted_out_at', 'created_at']),
            'tags' => $customer->tags->pluck('name'),
            'bookings' => $customer->bookings->map(fn ($b) => ['service' => $b->service?->label('en'), 'starts_at' => $b->starts_at, 'status' => $b->status]),
            'conversations' => $chats->map(fn ($c) => ['channel' => $c->channel, 'messages' => $c->messages->map(fn ($m) => ['at' => $m->created_at, 'from' => $m->sender_type, 'text' => $m->body])]),
        ];

        return response()->streamDownload(fn () => print json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'customer-'.$customer->id.'.json', ['Content-Type' => 'application/json']);
    }

    /** Join another record of the same person into this one. */
    public function merge(Request $request, int $id, \App\Services\CustomerMerger $merger): RedirectResponse
    {
        $data = $request->validate(['other_id' => ['required', 'integer']]);
        $keep = Customer::query()->findOrFail($id);
        $drop = Customer::query()->whereKeyNot($keep->id)->find($data['other_id']);

        if ($drop === null) {
            return back()->withErrors(['other_id' => __('Choose a different customer to join with this one.')]);
        }

        $name = $drop->name ?: $drop->phone ?: '#'.$drop->id;
        $merger->merge($keep, $drop);

        return back()->with('status', __('Joined ":name" into this customer. Their chats, bookings, orders and tags are all here now.', ['name' => $name]));
    }

    /** A sample file to fill in. */
    public function importTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBFname,phone,email,language,tags,notes\n";
            echo "Amy Tan,60123456789,amy@example.com,en,VIP;First visit,Prefers afternoons\n";
            echo "陈小明,60111222333,,zh,,\n";
        }, 'customers-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Add customers from a spreadsheet (CSV). People already in the list (same phone) are updated, never duplicated. */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt']], ['file.mimes' => __('Please choose a CSV file (in Excel: File, Save as, CSV).')]);
        $agreed = $request->boolean('agreed');
        $result = (new \App\Services\CustomerImporter)->import((string) file_get_contents($request->file('file')->getRealPath()), $agreed);

        if ($result['error'] !== null) {
            return back()->withErrors(['file' => __($result['error'])]);
        }

        return back()->with('status', __('Imported: :new new, :updated updated, :skipped skipped.', ['new' => $result['new'], 'updated' => $result['updated'], 'skipped' => $result['skipped']]));
    }

    /** Delete a customer and their chats for good (privacy request). Future bookings are cancelled first. */
    public function erase(Request $request, int $id, \App\Booking\Bookings $bookings): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($id);

        foreach ($customer->bookings()->where('status', 'confirmed')->where('starts_at', '>', now())->get() as $booking) {
            $bookings->cancel($booking, 'staff');
        }

        $customer->conversations()->delete();   // their messages go with them
        $customer->delete();

        return redirect()->route('customers')->with('status', __('The customer and their chats were deleted.'));
    }

    /** Add or remove one tag for the ticked customers on the list. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'tag_id' => ['required', 'integer'],
            'action' => ['required', Rule::in(['add', 'remove'])],
        ]);

        $tag = Tag::query()->where('type', 'customer')->findOrFail($data['tag_id']);
        $customers = Customer::query()->whereIn('id', array_map('intval', $data['ids']))->get();

        foreach ($customers as $c) {
            $data['action'] === 'add' ? $c->tags()->syncWithoutDetaching([$tag->id]) : $c->tags()->detach($tag->id);
        }

        return back()->with('status', ($data['action'] === 'add' ? __('Added ":tag" for :n customer(s).', ['tag' => $tag->name, 'n' => $customers->count()]) : __('Removed ":tag" for :n customer(s).', ['tag' => $tag->name, 'n' => $customers->count()])));
    }
}
