@props([
    'variant' => 'neutral',
])

@php
    $variants = [
        'neutral' => '',
        'primary' => 'js-badge-primary',
        'success' => 'js-badge-success',
        'warning' => 'js-badge-warning',
        'danger' => 'js-badge-danger',
        'info' => 'js-badge-info',
    ];
@endphp

<span {{ $attributes->class([
    'js-badge',
    $variants[$variant] ?? $variants['neutral'],
]) }}>
    {{ $slot }}
</span>