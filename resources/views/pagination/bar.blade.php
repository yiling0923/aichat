@if ($paginator->hasPages())
    @php $total = method_exists($paginator, 'total') ? $paginator->total() : null; @endphp
    <nav class="pg" role="navigation" aria-label="Pages">
        <span class="pg-info">
            @if ($total !== null)Showing <b>{{ $paginator->firstItem() }}-{{ $paginator->lastItem() }}</b> of <b>{{ number_format($total) }}</b>@else Page <b>{{ $paginator->currentPage() }}</b>@endif
        </span>
        <span class="pg-btns">
            @if ($paginator->onFirstPage())<span class="btn small pg-off" aria-disabled="true">&larr; Previous</span>@else<a class="btn small" href="{{ $paginator->previousPageUrl() }}" rel="prev">&larr; Previous</a>@endif
            @if (method_exists($paginator, 'lastPage'))<span class="pg-page">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>@endif
            @if ($paginator->hasMorePages())<a class="btn small" href="{{ $paginator->nextPageUrl() }}" rel="next">Next &rarr;</a>@else<span class="btn small pg-off" aria-disabled="true">Next &rarr;</span>@endif
        </span>
    </nav>
@endif
