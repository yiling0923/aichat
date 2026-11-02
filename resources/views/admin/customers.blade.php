@extends('admin.layout')
@section('title', __('Customers'))
@section('content')
    <div class="page-head"><h1>{{ __('Customers') }}</h1>@if (auth()->user()->allows('customers_admin'))<span class="bar" style="gap:8px"><a class="btn" id="dl-all" href="{{ url('/admin/customers/download') }}?{{ http_build_query(array_filter(['q' => $search, 'score' => $score, 'language' => $language, 'tag' => $tag ?: null])) }}">{{ __('Download data') }}</a><button type="button" class="btn" id="open-import">{{ __('Import from a spreadsheet') }}</button></span>@endif</div>
    <p class="mute">{{ __('One record per person (matched by phone number). Open a customer to see their chats, bookings and notes. Lead level: Ready to book = booked or ready to buy, Interested = left their details, Just browsing = low interest.') }}</p>
    <form method="get" class="fbar">
        <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search name, phone or interest') }}">
        <select name="score" class="small">
            <option value="">{{ __('Any lead level') }}</option>
            @foreach (['hot', 'warm', 'cold'] as $s)<option value="{{ $s }}" @selected($score === $s)>{{ \App\Support\Lang::lead($s) }}</option>@endforeach
        </select>
        <select name="language" class="small">
            <option value="">{{ __('Any language') }}</option>
            @foreach (config('aichat.languages') as $l)<option value="{{ $l }}" @selected($language === $l)>{{ $l === 'zh' ? '中文' : ($l === 'en' ? 'English' : strtoupper($l)) }}</option>@endforeach
        </select>
        <select name="tag" class="small">
            <option value="0">{{ __('Any tag') }}</option>
            @foreach ($tags as $t)<option value="{{ $t->id }}" @selected($tag === $t->id)>{{ __($t->name) }}</option>@endforeach
        </select>
        <button class="btn" type="submit">{{ __('Filter') }}</button>
        @if ($search !== '' || $score || $language || $tag)<a class="btn" href="{{ route('customers') }}">{{ __('Reset') }}</a>@endif
        <span class="f-note">{{ $customers->total() === 1 ? __('1 customer') : __(':n customers', ['n' => number_format($customers->total())]) }}</span>
    </form>

    <form method="post" action="{{ url('/admin/customers/bulk') }}">
        @csrf
        @if ($tags->isNotEmpty() || auth()->user()->allows('customers_admin'))
            <div class="cust-bulk" id="cust-bulk" hidden>
                <b><span id="cb-n">0</span> {{ __('selected') }}</b>
                @if (auth()->user()->allows('customers_admin'))<button class="btn small" type="submit" formaction="{{ url('/admin/customers/download') }}" name="download" value="1">{{ __('Download') }}</button>@endif
                @if ($tags->isNotEmpty())
                    <span class="cb-sep"></span>
                    <select name="tag_id" class="small" aria-label="{{ __('Tag') }}">@foreach ($tags as $t)<option value="{{ $t->id }}">{{ __($t->name) }}</option>@endforeach</select>
                    <button class="btn small primary" name="action" value="add">{{ __('Add tag') }}</button>
                    <button class="btn small" name="action" value="remove">{{ __('Remove tag') }}</button>
                @endif
                <button type="button" class="ibtn cb-clear" id="cb-clear" aria-label="{{ __('Clear selection') }}" title="{{ __('Clear selection') }}">&times;</button>
            </div>
        @endif
        <table class="table">
            @php($th = fn ($k, $l) => \App\Support\SortLink::th($k, $l, $sort, $dir))
            <thead><tr><th><input type="checkbox" id="pick-all" aria-label="{{ __('Select all') }}"></th>{!! $th('name', __('Name')) !!}<th>{{ __('Phone') }}</th>{!! $th('interest', __('Interested in')) !!}<th>{{ __('Tags') }}</th><th>{{ __('Lang') }}</th>{!! $th('lead', __('Lead level')) !!}<th title="{{ __('Can we send promotional messages?') }}">{{ __('Promotions') }}</th>{!! $th('added', __('Added')) !!}</tr></thead>
            <tbody>
            @forelse ($customers as $c)
                <tr>
                    <td><input class="pick" type="checkbox" name="ids[]" value="{{ $c->id }}"></td>
                    <td><a href="{{ route('customers.show', $c->id) }}">{{ $c->name ?: __('(no name)') }}</a></td>
                    <td>@if (filled($c->phone)){{ $c->phone }}@else<span class="mute">-</span>@endif</td>
                    <td>@if (filled($c->interest)){{ $c->interest }}@else<span class="mute">-</span>@endif</td>
                    <td>@forelse ($c->tags as $t)<span class="chip" style="background:{{ $t->color }}">{{ __($t->name) }}</span>@empty<span class="mute">-</span>@endforelse</td>
                    <td class="nw">@if (blank($c->language))<span class="mute">-</span>@else{{ $c->language === 'zh' ? '中文' : ($c->language === 'en' ? 'EN' : strtoupper($c->language)) }}@endif</td>
                    <td><span title="{{ __(['hot' => 'Booked or ready to buy', 'warm' => 'Interested, left their details', 'cold' => 'Low interest'][$c->score] ?? 'Not set') }}" class="tag {{ $c->score === 'hot' ? 'hot' : ($c->score === 'warm' ? 'warn' : '') }}">{{ \App\Support\Lang::lead($c->score) }}</span></td>
                    <td>@if ($c->opted_out_at)<span class="tag warn">{{ __('Opted out') }}</span>@elseif ($c->consented_at)<span class="tag ok">{{ __('Allowed') }}</span>@else<span class="mute">{{ __('Not asked') }}</span>@endif</td>
                    <td class="nw">{{ \App\Support\Lang::fmt($c->created_at, 'd M Y', 'Y年n月j日') }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">{{ __('No customers match. Finish a booking in the test chat to add one.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </form>
    <div class="pager">{{ $customers->links('pagination.bar') }}</div>
@if (auth()->user()->allows('customers_admin'))
    <dialog id="import-dialog" class="dlg">
        <button type="button" class="dlg-x" aria-label="{{ __('Close') }}">&times;</button>
        <h3>{{ __('Import customers') }}</h3>
        <p class="mute" style="margin-top:4px">{{ __('Choose a CSV file. The first row names the columns: name, phone, email, language, tags, notes. People with a phone number that is already here are updated, not added twice. Up to 2000 rows.') }} <a href="{{ url('/admin/customers/import-template') }}">{{ __('Download a sample file') }}</a></p>
        <form method="post" action="{{ url('/admin/customers/import') }}" enctype="multipart/form-data">
            @csrf
            <label class="f" style="margin-top:12px"><span>{{ __('CSV file') }}</span><input type="file" name="file" accept=".csv,text/csv,text/plain" required></label>
            <label class="chk"><input type="checkbox" name="agreed" value="1"> {{ __('These people agreed to receive messages from us (broadcasts and automations). Leave this unticked if you are not sure.') }}</label>
            <div class="dlg-foot"><button class="btn primary" type="submit">{{ __('Import') }}</button></div>
        </form>
    </dialog>
    @push('scripts')
    <script>
    (function () {
      var d = document.getElementById('import-dialog'), b = document.getElementById('open-import');
      b.addEventListener('click', function () { d.showModal(); });
      d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('.dlg-x')) { d.close(); } });
    })();
    </script>
    @endpush
    @endif
@push('scripts')
<script>
(function () {
  var bar = document.getElementById('cust-bulk'), n = document.getElementById('cb-n'), all = document.getElementById('pick-all');
  if (!bar) { return; }
  var picks = function () { return document.querySelectorAll('.pick'); };
  function refresh() {
    var c = document.querySelectorAll('.pick:checked').length;
    n.textContent = c; bar.hidden = c === 0;
    if (all) { all.checked = c > 0 && c === picks().length; all.indeterminate = c > 0 && c < picks().length; }
  }
  picks().forEach(function (p) { p.addEventListener('change', refresh); });
  if (all) { all.addEventListener('change', function () { picks().forEach(function (p) { p.checked = all.checked; }); refresh(); }); }
  document.getElementById('cb-clear').addEventListener('click', function () { picks().forEach(function (p) { p.checked = false; }); refresh(); });
  refresh();
})();
</script>
@endpush
@endsection
