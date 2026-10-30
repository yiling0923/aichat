{{-- Bars for money. $rows = [['label' =>, 'n' => amount], ...], $cur = currency text. --}}
@php $max = max(0.01, collect($rows)->max('n') ?? 0.01); $total = collect($rows)->sum('n'); @endphp
<div class="rk">
@forelse ($rows as $i => $row)
    <div class="rk-row">
        <span class="rk-n">{{ $i + 1 }}</span>
        <div class="rk-body">
            <div class="rk-top"><span>{{ __($row['label']) }}</span><b>{{ $cur }} {{ number_format($row['n'], 2) }}@if ($total > 0 && count($rows) > 1)<small>{{ round($row['n'] / $total * 100) }}%</small>@endif</b></div>
            <div class="rk-track"><i style="width: {{ round($row['n'] / $max * 100) }}%"></i></div>
        </div>
    </div>
@empty
    <p class="mute">{{ __('No paid orders in this period.') }}</p>
@endforelse
</div>
