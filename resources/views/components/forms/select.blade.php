@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'id' => null,
    'help' => null,
    'required' => false,
    'disabled' => false,
    'multiple' => false,
    'errorBag' => 'default',
])

@php
    $fieldKey = trim(
        str_replace(['[', ']'], ['.', ''], $name),
        '.'
    );

    $fieldId = $id ?? str_replace('.', '-', $fieldKey);

    $fieldName = $multiple && !str_ends_with($name, '[]')
        ? $name . '[]'
        : $name;

    $selectedValue = old(
        $fieldKey,
        $value
    );

    $selectedValues = array_map(
        'strval',
        array_filter(
            (array) $selectedValue,
            fn ($item) => is_scalar($item)
        )
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

    <select
        id="{{ $fieldId }}"
        name="{{ $fieldName }}"
        @required($required)
        @disabled($disabled)
        @if ($multiple)
            multiple
        @endif
        aria-invalid="{{ $hasError ? 'true' : 'false' }}"
        @if (filled($describedBy))
            aria-describedby="{{ $describedBy }}"
        @endif
        {{ $attributes
            ->except(['aria-describedby', 'aria-invalid'])
            ->class([
                'js-select',
                'js-input-error' => $hasError,
            ])
        }}
    >
        @if (!$multiple && filled($placeholder))
            <option
                value=""
                @selected(
                    $selectedValue === null ||
                    $selectedValue === ''
                )
            >
                {{ $placeholder }}
            </option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option
                value="{{ $optionValue }}"
                @selected(
                    in_array(
                        (string) $optionValue,
                        $selectedValues,
                        true
                    )
                )
            >
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

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