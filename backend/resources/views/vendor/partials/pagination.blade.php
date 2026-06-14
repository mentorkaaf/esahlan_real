@if($paginator->hasPages())
<ul class="pagination">
    @if($paginator->onFirstPage())
    <li class="page-item disabled"><span class="page-link"><i class="fa-solid fa-chevron-left"></i></span></li>
    @else
    <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}"><i class="fa-solid fa-chevron-left"></i></a></li>
    @endif

    @foreach($elements as $element)
        @if(is_string($element))
        <li class="page-item disabled"><span class="page-link">...</span></li>
        @endif
        @if(is_array($element))
            @foreach($element as $page => $url)
            <li class="page-item {{ $page == $paginator->currentPage() ? 'active' : '' }}">
                <a class="page-link" href="{{ $url }}">{{ $page }}</a>
            </li>
            @endforeach
        @endif
    @endforeach

    @if($paginator->hasMorePages())
    <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}"><i class="fa-solid fa-chevron-right"></i></a></li>
    @else
    <li class="page-item disabled"><span class="page-link"><i class="fa-solid fa-chevron-right"></i></span></li>
    @endif
</ul>
@endif
