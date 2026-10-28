{{-- A tidy list of bars. $rows = [['label' =>, 'n' =>], ...]. $rank = show 1, 2, 3 in front (for "top" lists). --}}
@php $rank = $rank ?? false; $max = max(1, collect($rows)->max('n') ?? 1); $total = collect($rows)->sum('n'); @endphp
<div class="rk">
@forelse ($rows as $i => $row)
    <div class="rk-row" title="{{ $row['label'] }}: {{ $row['n'] }}{{ $total ? ' ('.round($row['n'] / $total * 100).'%)' : '' }}">
        @if ($rank)<span class="rk-n">{{ $i + 1 }}</span>@endif
        <div class="rk-body">
            <div class="rk-top"><span>{{ $row['label'] }}</span><b>{{ $row['n'] }}@if ($total && $total !== $row['n'])<small>{{ round($row['n'] / $total * 100) }}%</small>@endif</b></div>
            <div class="rk-track"><i style="width: {{ round($row['n'] / $max * 100) }}%"></i></div>
        </div>
    </div>
@empty
    <p class="mute">No data in this period.</p>
@endforelse
</div>
