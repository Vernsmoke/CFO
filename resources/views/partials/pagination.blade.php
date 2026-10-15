@if($paginator->hasPages())
    <nav class="cfo-pagination" aria-label="Results pages">
        @if($paginator->onFirstPage())<span aria-disabled="true">Previous</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>@endif
        <small>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</small>
        @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>@else<span aria-disabled="true">Next</span>@endif
    </nav>
@elseif($paginator->total())
    <p class="result-count">Showing {{ $paginator->total() }} {{ Illuminate\Support\Str::plural('record', $paginator->total()) }}</p>
@endif
