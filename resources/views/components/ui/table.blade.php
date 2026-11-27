@props([
    'headers' => [],
    'caption' => null,
    'label' => 'Listado de registros',
])

<div
    {{ $attributes->class(['js-table-wrapper']) }}
    role="region"
    aria-label="{{ $label }}"
    tabindex="0"
>
    <table class="js-table">
        @if (filled($caption))
            <caption class="sr-only">
                {{ $caption }}
            </caption>
        @endif

        @if (isset($head))
            <thead>
                {{ $head }}
            </thead>
        @elseif (count($headers) > 0)
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody>
            {{ $slot }}
        </tbody>

        @isset($footer)
            <tfoot>
                {{ $footer }}
            </tfoot>
        @endisset
    </table>
</div>