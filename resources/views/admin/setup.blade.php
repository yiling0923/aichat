@extends('admin.layout')
@section('title', 'Getting started')
@section('content')
    <h1>Getting started</h1>
    <p class="mute">Five steps to a working assistant. Nothing is saved to your business until you approve it.</p>

    <h2>1. Tell us about your business</h2>
    @if (! $draft)
        <form method="post" action="{{ url('/admin/setup/draft') }}" class="card">
            @csrf
            <p class="mute" style="margin-bottom:8px">Paste your website text or your price list (one service per line, like <code>Facial - RM 188 (60 min)</code>). {{ config('aichat.ai.provider') === 'off' ? 'The AI is off here, so we read simple price lines.' : 'The AI drafts services and answers from it.' }}</p>
            <div class="field"><label>Website text or price list</label><textarea name="about" rows="8" required>{{ old('about') }}</textarea></div>
            <div class="field"><label>Address (optional)</label><input type="text" name="address" value="{{ old('address', $address) }}"></div>
            <button class="btn primary" type="submit">Draft my setup</button>
        </form>
    @else
        <form method="post" action="{{ url('/admin/setup/approve') }}" class="card">
            @csrf
            <p class="mute" style="margin-bottom:8px">Draft made {{ $draft['source'] === 'ai' ? 'by the AI' : 'from your price lines' }}. Untick anything wrong and fix the rest.</p>
            <table class="table">
                <thead><tr><th>Add</th><th>Service (English)</th><th>Chinese (optional)</th><th>Minutes</th><th>Price</th></tr></thead>
                <tbody>
                @forelse ($draft['services'] as $i => $s)
                    <tr>
                        <td><input type="checkbox" name="services[{{ $i }}][include]" value="1" checked></td>
                        <td><input type="text" name="services[{{ $i }}][name_en]" value="{{ $s['name_en'] }}"></td>
                        <td><input type="text" name="services[{{ $i }}][name_zh]" value="{{ $s['name_zh'] }}"></td>
                        <td><input type="number" name="services[{{ $i }}][duration_min]" value="{{ $s['duration_min'] }}" style="width:80px"></td>
                        <td><input type="number" step="0.01" name="services[{{ $i }}][price]" value="{{ $s['price'] }}" style="width:100px"></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mute empty">No services found.</td></tr>
                @endforelse
                </tbody>
            </table>
            @foreach ($draft['faqs'] as $i => $f)
                <div class="field" style="margin-top:12px">
                    <label><input type="checkbox" name="faqs[{{ $i }}][include]" value="1" checked> Answer for the AI</label>
                    <input type="text" name="faqs[{{ $i }}][title]" value="{{ $f['title'] }}" style="margin-bottom:6px">
                    <textarea name="faqs[{{ $i }}][answer]" rows="2">{{ $f['answer'] }}</textarea>
                </div>
            @endforeach
            <div class="field" style="margin-top:12px"><label>Address</label><input type="text" name="address" value="{{ $address }}"></div>
            <button class="btn primary" type="submit">Approve and save</button>
        </form>
        <form method="post" action="{{ url('/admin/setup/discard') }}" style="margin-top:8px">@csrf<button class="btn" type="submit">Discard draft</button></form>
    @endif
    <p class="mute" style="margin-top:6px">Saved so far: <b>{{ $services }}</b> service(s). Opening hours are in <a href="{{ route('booking.setup') }}">Booking setup</a>.</p>

    <h2>2. Connect your calendar and WhatsApp</h2>
    <div class="card">
        <ul>
            <li><span>Google Calendar (so booked times are never double-booked)</span><a class="btn small" href="{{ route('booking.setup') }}">Open Booking setup</a></li>
            <li><span>WhatsApp, Instagram or Messenger @if ($channels) <span class="tag ok">{{ $channels }} connected</span> @else <span class="tag warn">not connected</span> @endif</span><a class="btn small" href="{{ route('channels') }}">Open Channels (use Check connection)</a></li>
        </ul>
        <p class="mute">Both are optional while you try things out: the website chat works straight away.</p>
    </div>

    <h2>3. Try your chat</h2>
    <div class="card"><a class="btn primary" href="{{ $chatUrl }}" target="_blank">Open my test chat</a> <span class="mute">Ask for prices, book a time, ask for staff.</span></div>

    <h2>4. Choose a plan</h2>
    <div class="card">
        <span class="mute">Current: <b>{{ str_replace('_', ' ', $planState) }}</b></span>
        <a class="btn small" href="{{ route('billing') }}">Plan &amp; billing</a>
    </div>

    <h2>5. Go live</h2>
    <div class="card">
        @if ($business->setup_done_at)
            <p><span class="tag ok">Live</span> Share this link with customers: <a href="{{ $chatUrl }}">{{ $chatUrl }}</a></p>
        @else
            <form method="post" action="{{ url('/admin/setup/go-live') }}">@csrf<button class="btn primary" type="submit">I am ready, go live</button></form>
        @endif
    </div>
@endsection
