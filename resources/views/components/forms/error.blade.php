@props([
    'name',
    'id' => null,
    'message' => null,
    'errorBag' => 'default',
])

@php
    $fieldKey = trim(
        str_replace(['[', ']'], ['.', ''], $name),
        '.'
    );

    $fieldId = $id ?? (
        str_replace('.', '-', $fieldKey) . '-error'
    );

    $errorMessage = $message ?? $errors
        ->getBag($errorBag)
        ->first($fieldKey);
@endphp

@if (filled($errorMessage))
    <p
        id="{{ $fieldId }}"
        role="alert"
        {{ $attributes->class(['js-field-error']) }}
    >
        {{ $errorMessage }}
    </p>
@endif