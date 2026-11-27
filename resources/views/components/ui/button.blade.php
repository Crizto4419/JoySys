@props([
    'variant' => 'primary',
    'size' => 'normal',
    'type' => 'button',
    'icon' => null,
    'href' => null,
    'disabled' => false,
])

@php
    $variants = [
        'primary' => 'js-button-primary',
        'secondary' => 'js-button-secondary',
        'ghost' => 'js-button-ghost',
        'danger' => 'js-button-danger',
    ];

    $sizes = [
        'normal' => '',
        'sm' => 'js-button-sm',
        'icon' => 'js-button-icon',
    ];

    $classes = [
        'js-button',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['normal'],
    ];

    $buttonType = in_array(
        $type,
        ['button', 'submit', 'reset'],
        true
    ) ? $type : 'button';
@endphp

@if (filled($href) && !$disabled)
    <a
        href="{{ $href }}"
        {{ $attributes->class($classes) }}
    >
        @if (filled($icon))
            <x-layout.icon
                :name="$icon"
                :size="$size === 'sm' ? 16 : 20"
            />
        @endif

        {{ $slot }}
    </a>
@else
    <button
        type="{{ $buttonType }}"
        @disabled($disabled)
        {{ $attributes->class($classes) }}
    >
        @if (filled($icon))
            <x-layout.icon
                :name="$icon"
                :size="$size === 'sm' ? 16 : 20"
            />
        @endif

        {{ $slot }}
    </button>
@endif