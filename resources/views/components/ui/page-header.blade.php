@props([
    'title',
    'description' => null,
])

<header {{ $attributes->class([
    'flex flex-wrap items-start justify-between gap-4',
]) }}>
    <div class="min-w-0">
        <h1 class="js-page-title">
            {{ $title }}
        </h1>

        @if (filled($description))
            <p class="js-subtitle mt-2">
                {{ $description }}
            </p>
        @endif
    </div>

    @isset($actions)
        <div class="js-actions">
            {{ $actions }}
        </div>
    @endisset
</header>