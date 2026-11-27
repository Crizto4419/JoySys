@props(['name', 'size' => 20])

<svg
    xmlns="http://www.w3.org/2000/svg"
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
    {{ $attributes->class(['js-icon']) }}
>
    @switch($name)

        @case('gem')
            <path d="M6 3h12l4 6-10 12L2 9z"/>
            <path d="M2 9h20M6 3l6 18 6-18"/>
            @break

        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
            @break

        @case('package')
            <path d="m12 3 9 5v8l-9 5-9-5V8z"/>
            <path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5"/>
            @break

        @case('boxes')
            <path d="m12 2 9 5-9 5-9-5z"/>
            <path d="m3 12 9 5 9-5M3 17l9 5 9-5"/>
            @break

        @case('alert')
            <path d="M10.3 3.9 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
            <path d="M12 9v4M12 17h.01"/>
            @break

        @case('tag')
            <path d="M3 3h8l10 10-8 8L3 11z"/>
            <circle cx="7.5" cy="7.5" r="1"/>
            @break

        @case('contact')
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <circle cx="12" cy="9" r="3"/>
            <path d="M6 18a6 6 0 0 1 12 0"/>
            @break

        @case('cart')
            <path d="M2 3h3l3 12h11l3-9H6"/>
            <circle cx="9" cy="20" r="1"/>
            <circle cx="19" cy="20" r="1"/>
            @break

        @case('credit-card')
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <path d="M2 10h20M6 16h4"/>
            @break

        @case('wallet')
            <rect x="3" y="5" width="18" height="15" rx="2"/>
            <path d="M3 8V5l14-3v3M21 10h-6a3 3 0 0 0 0 6h6"/>
            <path d="M16 13h.01"/>
            @break

        @case('chart')
            <path d="M3 3v18h18M8 16v-4M13 16V7M18 16V4"/>
            @break

        @case('store')
            <path d="m3 9 2-6h14l2 6M3 9h18M5 9v12h14V9M9 21v-7h6v7"/>
            @break

        @case('users')
            <circle cx="9" cy="7" r="4"/>
            <path d="M2 21v-3a7 7 0 0 1 14 0v3M17 3a4 4 0 0 1 0 8M22 21v-3a7 7 0 0 0-4-6"/>
            @break

        @case('shield')
            <path d="m12 2 9 4v6c0 5-4 8-9 10-5-2-9-5-9-10V6z"/>
            <path d="m8 12 3 3 5-6"/>
            @break

        @case('settings')
            <circle cx="12" cy="12" r="8"/>
            <circle cx="12" cy="12" r="3"/>
            <path d="M12 1v3M12 20v3M1 12h3M20 12h3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>
            @break

        @case('panel')
            <rect x="3" y="4" width="18" height="16" rx="2"/>
            <path d="M9 4v16"/>
            @break

        @case('menu')
            <path d="M3 6h18M3 12h18M3 18h18"/>
            @break

        @case('close')
            <path d="m6 6 12 12M18 6 6 18"/>
            @break

        @case('sun')
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2"/>
            @break

        @case('moon')
            <path d="M21 13A9 9 0 0 1 11 3a9 9 0 1 0 10 10z"/>
            @break

        @case('logout')
            <path d="M10 3H4v18h6M9 12h12m-5-5 5 5-5 5"/>
            @break

        @case('lock')
            <rect x="5" y="10" width="14" height="11" rx="2"/>
            <path d="M8 10V6a4 4 0 0 1 8 0v4"/>
            @break

        @case('clock')
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 7v5l3 2"/>
            @break

        @default
            <circle cx="12" cy="12" r="9"/>

    @endswitch
</svg>