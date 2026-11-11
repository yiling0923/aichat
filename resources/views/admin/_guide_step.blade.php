<div class="card gd-step {{ $s['done'] ? 'done' : '' }} {{ $isNext ? 'next' : '' }}" id="step-{{ $s['key'] }}">
    <span class="gd-n">@if ($s['done'])&#10003;@else{{ $n }}@endif</span>
    <div class="gd-body">
        <div class="gd-title"><b>{{ __($s['title']) }}</b>
            @if ($isNext)<span class="tag ok">{{ __('Next up') }}</span>@endif
            @if ($s['optional'])<span class="tag">{{ __('Optional') }}</span>@endif
            <span class="gd-min">{{ __('About :n min', ['n' => $s['minutes']]) }}</span></div>
        <p class="mute">{{ __($s['why']) }}</p>
    </div>
    <div class="gd-act">
        @unless ($s['done'] && $s['auto'])
            <a class="btn small {{ $s['done'] ? '' : 'primary' }}" href="{{ route('guide.go', $s['key']) }}">{{ $s['done'] ? __('Open') : __($s['cta']) }}</a>
        @endunless
        @if ($s['done'] && $s['auto'])
            <span class="gd-auto">{{ __('Done automatically') }}</span>
        @else
            <form method="post" action="{{ url('/admin/guide/'.$s['key']) }}">@csrf<button type="submit" class="gd-link">{{ $s['done'] ? __('Mark as not done') : __('Mark as done') }}</button></form>
        @endif
    </div>
</div>
