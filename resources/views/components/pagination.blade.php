@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 border-t border-ink-700 px-5 py-3 text-sm" aria-label="Pagination">
        <span class="text-ink-400">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </span>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm opacity-40">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary btn-sm" rel="prev">Previous</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary btn-sm" rel="next">Next</a>
            @else
                <span class="btn btn-secondary btn-sm opacity-40">Next</span>
            @endif
        </div>
    </nav>
@endif
