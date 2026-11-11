@php
    $gKey = session('guide_active');
    if ($gKey && auth()->check() && auth()->user()->role === 'owner') {
        $gSteps = collect(app(\App\Support\SetupGuide::class)->steps(auth()->user()->business, auth()->user()));
        $gReq = $gSteps->reject(fn ($s) => $s['optional'])->values();
        $gAt = $gReq->search(fn ($s) => $s['key'] === $gKey);
        $gCur = $gAt === false ? null : $gReq[$gAt];
        $gPrev = $gAt ? $gReq[$gAt - 1] : null;
        $gOn = $gCur && rtrim(strtok($gCur['url'], '?#'), '/') === rtrim(request()->url(), '/');
        $gLast = $gAt !== false && $gAt === $gReq->count() - 1;
    } else { $gCur = null; }
@endphp
@if ($gCur)
<div class="gd-bar" role="region" aria-label="{{ __('Setup guide') }}">
    <div class="gd-bar-txt">
        <span class="gd-bar-n">{{ __('Step :a of :b', ['a' => $gAt + 1, 'b' => $gReq->count()]) }}</span>
        <b>{{ __($gCur['title']) }}</b>
        <span class="gd-bar-why">{{ __($gCur['why']) }}</span>
    </div>
    <div class="gd-bar-act">
        @unless ($gOn)<a class="btn small" href="{{ $gCur['url'] }}" @if ($gCur['key'] === 'chat') target="_blank" rel="noopener" @endif>{{ __($gCur['cta']) }}</a>@endunless
        @if ($gPrev)<a class="btn small" href="{{ route('guide.go', $gPrev['key']) }}">{{ __('Back') }}</a>@endif
        <form method="post" action="{{ route('guide.next') }}">@csrf<button type="submit" class="btn small primary">{{ $gLast ? __('Done, finish') : __('Done, next step') }} &rarr;</button></form>
        <form method="post" action="{{ route('guide.exit') }}">@csrf<button type="submit" class="gd-link">{{ __('Leave the guide') }}</button></form>
    </div>
</div>
@endif
