@props(['paginator'])

@if($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Navigasi halaman">
        <span>
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
            dari {{ $paginator->total() }}
        </span>
        <span class="pager-links">
            @if($paginator->onFirstPage())
                <span class="pager-btn is-disabled" aria-hidden="true">&lsaquo;</span>
            @else
                <a class="pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">&lsaquo;</a>
            @endif

            @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                @if($page === $paginator->currentPage())
                    <span class="pager-btn is-active" aria-current="page">{{ $page }}</span>
                @else
                    <a class="pager-btn" href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if($paginator->hasMorePages())
                <a class="pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">&rsaquo;</a>
            @else
                <span class="pager-btn is-disabled" aria-hidden="true">&rsaquo;</span>
            @endif
        </span>
    </nav>
@endif
