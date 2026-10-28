@extends('admin.layout')
@section('title', $customer->name ?: 'Customer')
@section('content')
    @php
        $levels = config('aichat.lead_levels');
        $digits = preg_replace('/\D+/', '', (string) $customer->phone);
        $name = $customer->name ?: 'No name yet';
        $local = fn ($d) => $d?->copy()->setTimezone($tz);
        $icons = ['booking' => '▦', 'order' => '$', 'survey' => '★', 'chat' => '✉', 'new' => '+'];
    @endphp
    <p class="crumb"><a href="{{ route('customers') }}">&larr; All customers</a></p>

    <div class="cust-head">
        <span class="cust-av">{{ mb_strtoupper(mb_substr($customer->name ?: '?', 0, 1)) }}</span>
        <div class="cust-id">
            <h1>{{ $name }}</h1>
            <div class="cust-sub">
                @if ($customer->phone)<span>{{ $customer->phone }}</span>@endif
                @if ($customer->email)<span>{{ $customer->email }}</span>@endif
                <span>Customer since {{ $local($customer->created_at)->format('j M Y') }}</span>
                @if ($customer->language)<span>{{ strtoupper($customer->language) }}</span>@endif
            </div>
            <div class="cust-tags">
                @if ($customer->score)<span class="tag {{ $customer->score === 'hot' ? 'hot' : ($customer->score === 'warm' ? 'warn' : '') }}">{{ $levels[$customer->score] }}</span>@endif
                @foreach ($customer->tags as $t)<span class="chip" style="background:{{ $t->color }}">{{ $t->name }}</span>@endforeach
                @if ($customer->opted_out_at)<span class="tag warn" title="They typed STOP. No promotional messages are sent.">Opted out of promotions</span>
                @elseif ($customer->consented_at)<span class="tag ok" title="They agreed to be contacted.">Can receive promotions</span>@endif
            </div>
        </div>
        <div class="cust-actions">
            @if ($latestChat)<a class="btn primary" href="{{ route('inbox', ['c' => $latestChat->id]) }}">Open chat</a>@endif
            @if ($digits)<a class="btn" href="https://wa.me/{{ $digits }}" target="_blank" rel="noopener">WhatsApp</a>@endif
            <button type="button" class="btn" id="edit-customer">Edit</button>
            @if (auth()->user()->canManage())
                <details class="menu">
                    <summary class="btn" aria-label="More">&#8943;</summary>
                    <div class="menu-body">
                        <a href="{{ url('/admin/customers/'.$customer->id.'/export') }}">Download their data</a>
                        <form method="post" action="{{ url('/admin/customers/'.$customer->id.'/erase') }}" onsubmit="return confirm('Delete this customer and all their chats for good? This cannot be undone.')">@csrf<button type="submit" class="menu-danger">Delete their data</button></form>
                    </div>
                </details>
            @endif
        </div>
    </div>

    <div class="kpis main">
        <div class="kpi"><span class="kpi-l">Spent</span><b>{{ $currency }} {{ number_format($paidTotal, 2) }}</b><span class="kpi-s">{{ $orders->where('status', 'paid')->count() }} paid {{ $orders->where('status', 'paid')->count() === 1 ? 'order' : 'orders' }}@if ($orders->where('status', 'pending')->count()) · <b class="td-late">{{ $currency }} {{ number_format($orders->where('status', 'pending')->sum('amount'), 2) }} unpaid</b>@endif</span></div>
        <div class="kpi"><span class="kpi-l">Bookings</span><b>{{ $bookingCount }}</b><span class="kpi-s">{{ $upcoming ? 'next: '.$local($upcoming->starts_at)->format('D j M, H:i') : 'none coming up' }}</span></div>
        <div class="kpi"><span class="kpi-l">Last visit</span><b>{{ $lastVisit ? $local($lastVisit->starts_at)->format('j M Y') : '-' }}</b><span class="kpi-s">{{ $lastVisit ? $lastVisit->service->label('en') : 'no visit yet' }}</span></div>
        <div class="kpi"><span class="kpi-l">Satisfaction</span><b>{{ $rating === null ? '-' : number_format($rating, 1).' / 5' }}</b><span class="kpi-s">{{ $rating === null ? 'no rating yet' : 'from their surveys' }}</span></div>
    </div>

    <div class="cust-cols">
        <section class="card">
            <h3>Activity</h3>
            <ul class="timeline">
                @foreach ($events as $e)
                    @php $at = $local($e['at']); $future = $e['at']->isFuture(); @endphp
                    <li class="{{ $future ? 'future' : '' }}">
                        <span class="ti">{{ $icons[$e['type']] }}</span>
                        <div class="tb">
                            @if ($e['url'])<a href="{{ $e['url'] }}"><b>{{ $e['title'] }}</b></a>@else<b>{{ $e['title'] }}</b>@endif
                            @if ($e['sub'])<small class="mute">{{ $e['sub'] }}</small>@endif
                        </div>
                        <time title="{{ $at->format('D j M Y, H:i') }}">{{ $future ? 'Upcoming · ' : '' }}{{ $at->format('j M Y') }}</time>
                    </li>
                @endforeach
            </ul>
        </section>

        <aside>
            <div class="card">
                <h3>Follow-ups</h3>
                @forelse ($tasks as $t)
                    <div class="task-line">
                        <span>{{ $t->title }}@if ($t->due_at)<br><small class="mute">due {{ $local($t->due_at)->format('j M, H:i') }}</small>@endif</span>
                        <form method="post" action="{{ url('/admin/tasks/'.$t->id.'/toggle') }}">@csrf<button class="btn small">Done</button></form>
                    </div>
                @empty
                    <p class="mute" style="margin:6px 0 10px">Nothing to follow up.</p>
                @endforelse
                <form method="post" action="{{ url('/admin/tasks') }}" class="task-add">
                    @csrf<input type="hidden" name="customer_id" value="{{ $customer->id }}">
                    <input type="text" name="title" placeholder="Add a follow-up, e.g. call about packages" required>
                    <input type="datetime-local" name="due_at" aria-label="Due">
                    <button class="btn small primary" type="submit">Add</button>
                </form>
            </div>

            <div class="card">
                <div class="bar spread"><h3>Notes</h3><span class="mute" style="font-size:11.5px">private</span></div>
                <p class="note-text">{{ $customer->notes ?: 'No notes yet.' }}</p>
                @if ($customer->interest || $customer->details)
                    <div class="kv">
                        @if ($customer->interest)<div><span>Asked about</span><b>{{ $customer->interest }}</b></div>@endif
                        @foreach ((array) $customer->details as $k => $v)<div><span>{{ ucfirst(str_replace('_', ' ', $k)) }}</span><b>{{ $v }}</b></div>@endforeach
                    </div>
                @endif
            </div>
        </aside>
    </div>

    <dialog id="edit-dialog" class="dlg wide">
        <button type="button" class="dlg-x" aria-label="Close">&times;</button>
        <h3>Edit customer</h3>
        <form method="post" action="{{ url('/admin/customers/'.$customer->id) }}">
            @csrf
            <div class="grid2" style="margin-top:12px">
                <label class="f"><span>Name</span><input type="text" name="name" value="{{ old('name', $customer->name) }}"></label>
                <label class="f"><span>Phone</span><input type="text" name="phone" value="{{ old('phone', $customer->phone) }}"></label>
                <label class="f"><span>Email</span><input type="email" name="email" value="{{ old('email', $customer->email) }}"></label>
                <label class="f"><span>Language</span>
                    <select name="language"><option value="">-</option>@foreach (config('aichat.languages') as $l)<option value="{{ $l }}" @selected($customer->language === $l)>{{ strtoupper($l) }}</option>@endforeach</select></label>
                <label class="f"><span>Lead level</span>
                    <select name="score"><option value="">-</option>@foreach (['hot', 'warm', 'cold'] as $s)<option value="{{ $s }}" @selected($customer->score === $s)>{{ $levels[$s] }}</option>@endforeach</select></label>
            </div>
            <div class="f"><span>Tags</span>
                <div class="tag-pick">
                @forelse ($allTags as $t)
                    <label class="tp"><input type="checkbox" name="tags[]" value="{{ $t->id }}" @checked($customer->tags->contains('id', $t->id))><span class="chip" style="background:{{ $t->color }}">{{ $t->name }}</span></label>
                @empty
                    <small class="mute">No customer tags yet. @if (auth()->user()->canManage())<a href="{{ route('tags') }}">Create some</a>.@endif</small>
                @endforelse
                </div>
            </div>
            <label class="f"><span>Notes (private, only your team sees them)</span><textarea name="notes" rows="4">{{ old('notes', $customer->notes) }}</textarea></label>
            <div class="dlg-foot"><button class="btn primary" type="submit">Save</button></div>
        </form>
    </dialog>
@endsection
@push('scripts')
<script>
(function () {
  var dlg = document.getElementById('edit-dialog');
  document.getElementById('edit-customer').addEventListener('click', function () { dlg.showModal(); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('.dlg-x')) { dlg.close(); } });
  @if ($errors->any()) dlg.showModal(); @endif
  document.addEventListener('click', function (e) { if (!e.target.closest('details.menu')) { document.querySelectorAll('details.menu').forEach(function (d) { d.open = false; }); } });
})();
</script>
@endpush
