{{-- A list of horizontal bars. $rows = [['label' =>, 'n' =>], ...]. One colour, the numbers are always shown. --}}
@php $max = max(1, collect($rows)->max('n') ?? 1); $total = collect($rows)->sum('n'); @endphp
@forelse ($rows as $row)
    <div class="hbar" title="{{ $row['label'] }}: {{ $row['n'] }}{{ $total ? ' ('.round($row['n'] / $total * 100).'%)' : '' }}">
        <div class="hbar-top"><span>{{ $row['label'] }}</span><b>{{ $row['n'] }}</b></div>
        <div class="hbar-track"><div class="hbar-fill" style="width: {{ round($row['n'] / $max * 100) }}%"></div></div>
    </div>
@empty
    <p class="mute">No data in this period.</p>
@endforelse
