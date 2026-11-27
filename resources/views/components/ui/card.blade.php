@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->class(['js-card']) }}>
    @if (filled($title) || filled($description) || isset($actions))
        <div class="js-card-header">
            <div class="min-w-0">
                @if (filled($title))
                    <h2 class="js-card-title">
                        {{ $title }}
                    </h2>
                @endif

                @if (filled($description))
                    <p class="js-card-description">
                        {{ $description }}
                    </p>
                @endif
            </div>

            @isset($actions)
                <div class="js-actions">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    {{ $slot }}

    @isset($footer)
        <hr class="js-divider">

        <div>
            {{ $footer }}
        </div>
    @endisset
</section>