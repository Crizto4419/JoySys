@extends('layouts.app')

@section('title', 'Catálogos')

@section('content')
<div class="js-stack">
    <x-ui.page-header
        title="Catálogos"
        description="Administración de categorías, metales y purezas."
    />

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ([
            ['categorias', 'Categorías', 'Organización de productos.'],
            ['metales', 'Metales', 'Materiales utilizados en joyería.'],
            ['purezas', 'Purezas', 'Clasificación de kilatajes y leyes.'],
        ] as [$clave, $titulo, $descripcion])
            <x-ui.card :title="$titulo" :description="$descripcion">
                <div class="py-4">
                    <p class="text-3xl font-semibold">
                        {{ $resumen[$clave]['total'] ?? $resumen[$clave.'_total'] ?? '—' }}
                    </p>
                </div>

                <x-ui.button :href="route('catalogos.'.$clave.'.index')">
                    Consultar {{ mb_strtolower($titulo) }}
                </x-ui.button>
            </x-ui.card>
        @endforeach
    </div>
</div>
@endsection
