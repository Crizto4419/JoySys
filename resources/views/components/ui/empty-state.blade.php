@props([
    'title' => 'Sin registros',
    'description' => null,
    'icon' => 'package',
])

<div {{ $attributes->class(['js-empty-state']) }}>
    @if (filled($icon))
        <div class="js-text-muted">
            <x-layout.icon
                :name="$icon"
                :size="40"
            />
        </div>
    @endif

    <p class="js-empty-state-title">
        {{ $title }}
    </p>

    @if (filled($description))
        <p class="js-subtitle max-w-md">
            {{ $description }}
        </p>
    @endif

    {{ $slot }}

    @isset($actions)
        <div class="js-actions justify-center">
            {{ $actions }}
        </div>
    @endisset
</div>