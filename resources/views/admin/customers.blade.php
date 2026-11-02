@extends('admin.layout')
@section('title', __('Customers'))
@section('content')
    <div class="page-head"><h1>{{ __('Customers') }}</h1>@if (auth()->user()->allows('customers_admin'))<button type="button" class="btn" id="open-import">{{ __('Import from a spreadsheet') }}</button>@endif</div>
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
        @if ($tags->isNotEmpty())
            <div class="bar">
                <span class="mute">{{ __('With the ticked customers:') }}</span>
                <select name="tag_id" class="small">@foreach ($tags as $t)<option value="{{ $t->id }}">{{ __($t->name) }}</option>@endforeach</select>
                <button class="btn small" name="action" value="add">{{ __('Add tag') }}</button>
                <button class="btn small" name="action" value="remove">{{ __('Remove tag') }}</button>
            </div>
        @endif
        <table class="table">
            @php
                $th = function (string $key, string $label) use ($sort, $dir) {
                    $on = $sort === $key; $next = $on && $dir === 'asc' ? 'desc' : 'asc';
                    $url = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null]);
                    return '<th class="sortable '.($on ? 'sorted' : '').'" aria-sort="'.($on ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none').'"><a href="'.e($url).'">'.e($label).'<span class="arrows" aria-hidden="true"><i class="'.($on && $dir === 'asc' ? 'on' : '').'">▲</i><i class="'.($on && $dir === 'desc' ? 'on' : '').'">▼</i></span></a></th>';
                };
            @endphp
            <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.pick').forEach(c => c.checked = this.checked)"></th>{!! $th('name', __('Name')) !!}<th>{{ __('Phone') }}</th>{!! $th('interest', __('Interested in')) !!}<th>{{ __('Tags') }}</th><th>{{ __('Lang') }}</th>{!! $th('lead', __('Lead level')) !!}<th title="{{ __('Can we send promotional messages?') }}">{{ __('Promotions') }}</th>{!! $th('added', __('Added')) !!}</tr></thead>
            <tbody>
            @forelse ($customers as $c)
                <tr>
                    <td><input class="pick" type="checkbox" name="ids[]" value="{{ $c->id }}"></td>
                    <td><a href="{{ route('customers.show', $c->id) }}">{{ $c->name ?: __('(no name)') }}</a></td>
                    <td>{{ $c->phone ?: '-' }}</td>
                    <td>{{ $c->interest ?: '-' }}</td>
                    <td>@foreach ($c->tags as $t)<span class="chip" style="background:{{ $t->color }}">{{ __($t->name) }}</span>@endforeach</td>
                    <td class="nw">{{ $c->language === 'zh' ? '中文' : ($c->language === 'en' ? 'EN' : strtoupper($c->language ?: '-')) }}</td>
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
@endsection
