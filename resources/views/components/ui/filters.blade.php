@props([
    'action',
    'search' => true,
    'searchName' => 'buscar',
    'searchValue' => null,
    'searchLabel' => 'Buscar',
    'searchPlaceholder' => 'Buscar registros...',
    'submitLabel' => 'Aplicar filtros',
    'resetLabel' => 'Limpiar',
    'resetUrl' => null,
])

@php
    $currentSearch = $searchValue
        ?? request()->query($searchName, '');

    $currentSearch = is_scalar($currentSearch)
        ? (string) $currentSearch
        : '';

    $clearUrl = $resetUrl ?? $action;
@endphp

<form
    method="GET"
    action="{{ $action }}"
    {{ $attributes
        ->except(['method', 'action'])
        ->class(['js-filters'])
    }}
>
    <div class="js-filters-fields">
        @if ($search)
            <div class="js-field js-filter-search">
                <label
                    for="filter-{{ $searchName }}"
                    class="js-label"
                >
                    {{ $searchLabel }}
                </label>

                <input
                    id="filter-{{ $searchName }}"
                    name="{{ $searchName }}"
                    type="search"
                    value="{{ $currentSearch }}"
                    placeholder="{{ $searchPlaceholder }}"
                    class="js-input"
                    maxlength="150"
                >
            </div>
        @endif

        {{ $slot }}
    </div>

    <div class="js-filters-actions">
        <x-ui.button
            type="submit"
            variant="primary"
        >
            {{ $submitLabel }}
        </x-ui.button>

        <x-ui.button
            variant="secondary"
            :href="$clearUrl"
        >
            {{ $resetLabel }}
        </x-ui.button>
    </div>
</form>