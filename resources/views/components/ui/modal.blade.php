@props([
    'id',
    'title',
    'description' => null,
    'size' => 'md',
    'closeOnBackdrop' => true,
    'closeOnEscape' => true,
])

@php
    $sizes = [
        'sm' => 'js-modal-sm',
        'md' => 'js-modal-md',
        'lg' => 'js-modal-lg',
        'xl' => 'js-modal-xl',
    ];
@endphp

<dialog
    id="{{ $id }}"
    data-js-modal
    data-close-on-backdrop="{{ $closeOnBackdrop ? 'true' : 'false' }}"
    data-close-on-escape="{{ $closeOnEscape ? 'true' : 'false' }}"
    aria-labelledby="{{ $id }}-title"
    @if (filled($description))
        aria-describedby="{{ $id }}-description"
    @endif
    {{ $attributes->class([
        'js-modal',
        $sizes[$size] ?? $sizes['md'],
    ]) }}
>
    <div class="js-modal-panel">
        <header class="js-modal-header">
            <div class="min-w-0">
                <h2
                    id="{{ $id }}-title"
                    class="js-section-title"
                >
                    {{ $title }}
                </h2>

                @if (filled($description))
                    <p
                        id="{{ $id }}-description"
                        class="js-subtitle mt-2"
                    >
                        {{ $description }}
                    </p>
                @endif
            </div>

            <x-ui.button
                variant="ghost"
                size="icon"
                icon="close"
                data-modal-close
                aria-label="Cerrar ventana"
                title="Cerrar ventana"
            />
        </header>

        <div class="js-modal-body">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="js-modal-footer">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</dialog>