@if ($paginator->hasPages())
<nav>
    <ul class="pagination">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <li><span style="opacity:.4;cursor:not-allowed;"><i class="fas fa-chevron-left"></i></span></li>
        @else
            <li><a href="{{ $paginator->previousPageUrl() }}"><i class="fas fa-chevron-left"></i></a></li>
        @endif

        {{-- Pages --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <li><span>…</span></li>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <li class="active"><span>{{ $page }}</span></li>
                    @else
                        <li><a href="{{ $url }}">{{ $page }}</a></li>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <li><a href="{{ $paginator->nextPageUrl() }}"><i class="fas fa-chevron-right"></i></a></li>
        @else
            <li><span style="opacity:.4;cursor:not-allowed;"><i class="fas fa-chevron-right"></i></span></li>
        @endif
    </ul>
</nav>
@endif
