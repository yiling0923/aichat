{{-- Bars per day. $days = list of rows, $key = which number, $title. Thin bars, rounded tops, light grid, a tooltip per bar. --}}
@php
    $values = array_column($days, $key);
    $max = max(1, max($values ?: [1]));
    $nice = $max <= 4 ? $max : (int) (ceil($max / 4) * 4);
    $w = 640; $h = 150; $padL = 34; $padB = 22; $padT = 10; $n = max(1, count($days));
    $slot = ($w - $padL) / $n; $bar = max(1.5, min(14, $slot - 2));
@endphp
<svg viewBox="0 0 {{ $w }} {{ $h + $padB + $padT }}" class="trend" role="img" aria-label="{{ $title }} per day">
    @foreach ([0, .5, 1] as $g)
        @php $y = $padT + $h - $g * $h; @endphp
        <line x1="{{ $padL }}" x2="{{ $w }}" y1="{{ $y }}" y2="{{ $y }}" class="grid"/>
        <text x="{{ $padL - 4 }}" y="{{ $y + 3 }}" text-anchor="end" class="axis">{{ round($g * $nice) }}</text>
    @endforeach
    @foreach ($days as $i => $d)
        @php $v = $d[$key]; $bh = $v / $nice * $h; $x = $padL + $i * $slot + ($slot - $bar) / 2; @endphp
        <g class="tbar">
            <rect x="{{ $padL + $i * $slot }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $h }}" fill="transparent"/>
            @if ($v > 0)<rect x="{{ round($x, 2) }}" y="{{ round($padT + $h - $bh, 2) }}" width="{{ round($bar, 2) }}" height="{{ round($bh, 2) }}" rx="{{ min(3, $bar / 2) }}" class="fill"/>@endif
            <title>{{ \Carbon\Carbon::parse($d['date'])->format('D j M') }}: {{ $v }} {{ $title }}</title>
        </g>
    @endforeach
    @foreach ([0, (int) floor(($n - 1) / 2), $n - 1] as $i)
        @if (isset($days[$i]))<text x="{{ round($padL + $i * $slot + $slot / 2, 1) }}" y="{{ $padT + $h + 16 }}" text-anchor="{{ $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle') }}" class="axis">{{ \Carbon\Carbon::parse($days[$i]['date'])->format('j M') }}</text>@endif
    @endforeach
</svg>
