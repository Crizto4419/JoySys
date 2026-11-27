@props([
    'name',
    'label' => null,
    'value' => null,
    'rows' => 4,
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

    $fieldValue = old(
        $fieldKey,
        $value
    );

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

    <textarea
        id="{{ $fieldId }}"
        name="{{ $name }}"
        rows="{{ max(1, (int) $rows) }}"
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
                'js-textarea',
                'js-input-error' => $hasError,
            ])
        }}
    >{{ is_scalar($fieldValue) ? $fieldValue : '' }}</textarea>

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