@props([
    'paginator',
    'label' => 'Paginación de registros',
    'preserveQuery' => true,
])

@php
    if ($preserveQuery) {
        $paginator->withQueryString();
    }

    $hasTotal = $paginator instanceof
        \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    $currentPage = $paginator->currentPage();

    $firstPage = max(1, $currentPage - 2);

    $lastPage = $hasTotal
        ? min($paginator->lastPage(), $currentPage + 2)
        : $currentPage;
@endphp

<nav
    aria-label="{{ $label }}"
    {{ $attributes->class(['js-pagination']) }}
>
    <div class="js-pagination-summary">
        @if ($paginator->count() > 0)
            Mostrando
            <strong>{{ $paginator->firstItem() }}</strong>
            a
            <strong>{{ $paginator->lastItem() }}</strong>

            @if ($hasTotal)
                de
                <strong>{{ $paginator->total() }}</strong>
            @endif

            registros
        @else
            Sin registros disponibles
        @endif
    </div>

    @if ($paginator->hasPages())
        <div class="js-pagination-controls">
            <x-ui.button
                variant="secondary"
                size="sm"
                :href="$paginator->previousPageUrl()"
                :disabled="$paginator->onFirstPage()"
                aria-label="Página anterior"
            >
                Anterior
            </x-ui.button>

            @if ($hasTotal)
                <div class="js-pagination-pages">
                    @if ($firstPage > 1)
                        <a
                            href="{{ $paginator->url(1) }}"
                            class="js-pagination-page"
                            aria-label="Página 1"
                        >
                            1
                        </a>

                        @if ($firstPage > 2)
                            <span
                                class="js-pagination-ellipsis"
                                aria-hidden="true"
                            >
                                &hellip;
                            </span>
                        @endif
                    @endif

                    @for ($page = $firstPage; $page <= $lastPage; $page++)
                        @if ($page === $currentPage)
                            <span
                                class="js-pagination-page js-pagination-page-active"
                                aria-current="page"
                                aria-label="Página {{ $page }}, actual"
                            >
                                {{ $page }}
                            </span>
                        @else
                            <a
                                href="{{ $paginator->url($page) }}"
                                class="js-pagination-page"
                                aria-label="Página {{ $page }}"
                            >
                                {{ $page }}
                            </a>
                        @endif
                    @endfor

                    @if ($lastPage < $paginator->lastPage())
                        @if ($lastPage < $paginator->lastPage() - 1)
                            <span
                                class="js-pagination-ellipsis"
                                aria-hidden="true"
                            >
                                &hellip;
                            </span>
                        @endif

                        <a
                            href="{{ $paginator->url($paginator->lastPage()) }}"
                            class="js-pagination-page"
                            aria-label="Página {{ $paginator->lastPage() }}"
                        >
                            {{ $paginator->lastPage() }}
                        </a>
                    @endif
                </div>
            @endif

            <x-ui.button
                variant="secondary"
                size="sm"
                :href="$paginator->nextPageUrl()"
                :disabled="!$paginator->hasMorePages()"
                aria-label="Página siguiente"
            >
                Siguiente
            </x-ui.button>
        </div>
    @endif
</nav>