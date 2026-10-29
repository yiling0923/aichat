@if ($paginator->hasPages())
    @php $total = method_exists($paginator, 'total') ? $paginator->total() : null; @endphp
    <nav class="pg" role="navigation" aria-label="{{ __('Pages') }}">
        <span class="pg-info">
            @if ($total !== null){!! __('Showing :range of :total', ['range' => '<b>'.$paginator->firstItem().'-'.$paginator->lastItem().'</b>', 'total' => '<b>'.number_format($total).'</b>']) !!}@else {!! __('Page :n', ['n' => '<b>'.$paginator->currentPage().'</b>']) !!}@endif
        </span>
        <span class="pg-btns">
            @if ($paginator->onFirstPage())<span class="btn small pg-off" aria-disabled="true">&larr; {{ __('Previous') }}</span>@else<a class="btn small" href="{{ $paginator->previousPageUrl() }}" rel="prev">&larr; {{ __('Previous') }}</a>@endif
            @if (method_exists($paginator, 'lastPage'))<span class="pg-page">{{ __('Page :a of :b', ['a' => $paginator->currentPage(), 'b' => $paginator->lastPage()]) }}</span>@endif
            @if ($paginator->hasMorePages())<a class="btn small" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }} &rarr;</a>@else<span class="btn small pg-off" aria-disabled="true">{{ __('Next') }} &rarr;</span>@endif
        </span>
    </nav>
@endif
