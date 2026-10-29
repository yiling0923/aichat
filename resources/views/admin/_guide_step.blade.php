<div class="card gd-step {{ $s['done'] ? 'done' : '' }} {{ $isNext ? 'next' : '' }}" id="step-{{ $s['key'] }}">
    <span class="gd-n">@if ($s['done'])&#10003;@else{{ $n }}@endif</span>
    <div class="gd-body">
        <div class="gd-title"><b>{{ $s['title'] }}</b>
            @if ($isNext)<span class="tag ok">Next up</span>@endif
            @if ($s['optional'])<span class="tag">Optional</span>@endif
            <span class="gd-min">About {{ $s['minutes'] }} min</span></div>
        <p class="mute">{{ $s['why'] }}</p>
    </div>
    <div class="gd-act">
        @unless ($s['done'] && $s['auto'])
            <a class="btn small {{ $s['done'] ? '' : 'primary' }}" href="{{ $s['url'] }}" @if ($s['key'] === 'chat') target="_blank" rel="noopener" @endif>{{ $s['done'] ? 'Open' : $s['cta'] }}</a>
        @endunless
        @if ($s['done'] && $s['auto'])
            <span class="gd-auto">Done automatically</span>
        @else
            <form method="post" action="{{ url('/admin/guide/'.$s['key']) }}">@csrf<button type="submit" class="gd-link">{{ $s['done'] ? 'Mark as not done' : 'Mark as done' }}</button></form>
        @endif
    </div>
</div>
