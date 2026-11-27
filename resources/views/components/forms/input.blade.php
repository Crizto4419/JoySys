@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'id' => null,
    'help' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'errorBag' => 'default',
])

@php
    $fieldKey = trim(
        str_replace(['[', ']'], ['.', ''], $name),
        '.'
    );

    $fieldId = $id ?? str_replace('.', '-', $fieldKey);

    $allowedTypes = [
        'text',
        'email',
        'password',
        'number',
        'tel',
        'url',
        'date',
        'datetime-local',
        'time',
        'search',
        'file',
        'month',
        'week',
    ];

    $inputType = in_array($type, $allowedTypes, true)
        ? $type
        : 'text';

    $sensitive = in_array(
        $inputType,
        ['password', 'file'],
        true
    );

    $fieldValue = $sensitive
        ? null
        : old($fieldKey, $value);

    $hasError = $errors
        ->getBag($errorBag)
        ->has($fieldKey);

    $describedBy = implode(' ', array_filter([
        $attributes->get('aria-describedby'),
        filled($help) ? $fieldId . '-help' : null,
        $hasError ? $fieldId . '-error' : null,
    ]));
@endphp

<div class="js-field">
    @if (filled($label))
        <label
            for="{{ $fieldId }}"
            class="js-label"
        >
            {{ $label }}

            @if ($required)
                <span
                    class="js-text-danger"
                    aria-hidden="true"
                >*</span>
            @endif
        </label>
    @endif

    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="{{ $inputType }}"
        @if (!$sensitive)
            value="{{ is_scalar($fieldValue) ? $fieldValue : '' }}"
        @endif
        @required($required)
        @disabled($disabled)
        @readonly($readonly)
        aria-invalid="{{ $hasError ? 'true' : 'false' }}"
        @if (filled($describedBy))
            aria-describedby="{{ $describedBy }}"
        @endif
        {{ $attributes
            ->except(['aria-describedby', 'aria-invalid'])
            ->class([
                'js-input',
                'js-input-error' => $hasError,
            ])
        }}
    >

    @if (filled($help))
        <p
            id="{{ $fieldId }}-help"
            class="js-field-help"
        >
            {{ $help }}
        </p>
    @endif

    <x-forms.error
        :name="$fieldKey"
        :id="$fieldId . '-error'"
        :error-bag="$errorBag"
    />
</div>