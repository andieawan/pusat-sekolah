@if ($paginator->hasPages())
    <nav class="halaman" aria-label="Halaman">
        @if ($paginator->onFirstPage())
            <span class="nonaktif">‹ Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Sebelumnya</a>
        @endif

        <span>Halaman {{ $paginator->currentPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya ›</a>
        @else
            <span class="nonaktif">Berikutnya ›</span>
        @endif
    </nav>
@endif
