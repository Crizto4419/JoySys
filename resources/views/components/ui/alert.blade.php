@props([
    'variant' => 'info',
    'title' => null,
    'icon' => 'alert',
])

@php
    $variants = [
        'success' => 'js-alert-success',
        'warning' => 'js-alert-warning',
        'danger' => 'js-alert-danger',
        'info' => 'js-alert-info',
    ];

    $classes = [
        'js-alert',
        $variants[$variant] ?? $variants['info'],
    ];

    $role = in_array(
        $variant,
        ['danger', 'warning'],
        true
    ) ? 'alert' : 'status';
@endphp

<div
    role="{{ $role }}"
    {{ $attributes->class($classes) }}
>
    @if (filled($icon))
        <x-layout.icon
            :name="$icon"
            :size="20"
        />
    @endif

    <div class="min-w-0 flex-1">
        @if (filled($title))
            <p class="font-semibold">
                {{ $title }}
            </p>
        @endif

        <div @class(['mt-1' => filled($title)])>
            {{ $slot }}
        </div>
    </div>
</div>