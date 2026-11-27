@props([
    'name',
    'label' => null,
    'value' => '1',
    'checked' => false,
    'id' => null,
    'help' => null,
    'required' => false,
    'disabled' => false,
    'submitUnchecked' => true,
    'uncheckedValue' => '0',
    'errorBag' => 'default',
])

@php
    $fieldKey = trim(
        str_replace(['[', ']'], ['.', ''], $name),
        '.'
    );

    $isArray = str_ends_with($name, '[]');

    $fieldId = $id ?? (
        str_replace('.', '-', $fieldKey)
        . ($isArray ? '-' . substr(
            sha1((string) $value),
            0,
            10
        ) : '')
    );

    $oldInput = session()->getOldInput();

    if (session()->hasOldInput()) {
        $oldValue = data_get(
            $oldInput,
            $fieldKey
        );

        if ($isArray) {
            $isChecked = in_array(
                (string) $value,
                array_map(
                    'strval',
                    array_filter(
                        (array) $oldValue,
                        fn ($item) => is_scalar($item)
                    )
                ),
                true
            );
        } else {
            $isChecked = is_scalar($oldValue)
                && (string) $oldValue === (string) $value;
        }
    } else {
        $isChecked = (bool) $checked;
    }

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
    @if ($submitUnchecked && !$isArray && !$disabled)
        <input
            type="hidden"
            name="{{ $name }}"
            value="{{ $uncheckedValue }}"
        >
    @endif

    <div class="flex items-start gap-3">
        <input
            id="{{ $fieldId }}"
            type="checkbox"
            name="{{ $name }}"
            value="{{ $value }}"
            @checked($isChecked)
            @required($required)
            @disabled($disabled)
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            @if (filled($describedBy))
                aria-describedby="{{ $describedBy }}"
            @endif
            {{ $attributes
                ->except(['aria-describedby', 'aria-invalid'])
                ->class([
                    'js-checkbox',
                    'mt-0.5',
                    'shrink-0',
                    'js-input-error' => $hasError,
                ])
            }}
        >

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
    </div>

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