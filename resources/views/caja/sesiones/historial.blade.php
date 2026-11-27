@extends('layouts.app')

@section('title', 'Historial de sesiones')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header
            title="Historial de sesiones"
            description="Consulta tus sesiones de caja."
        />

        @include('caja._navegacion')
        @include('inventario._mensajes')

        <x-ui.card>
            <form method="GET" class="mb-4 flex gap-3">
                <select name="estado"
                        class="rounded border p-2 bg-transparent">
                    <option value="">Todos los estados</option>
                    <option value="abierta"
                        @selected(request('estado') === 'abierta')>
                        Abiertas
                    </option>
                    <option value="cerrada"
                        @selected(request('estado') === 'cerrada')>
                        Cerradas
                    </option>
                </select>

                <x-ui.button type="submit">Filtrar</x-ui.button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="p-3">Sesión</th>
                            <th class="p-3">Caja</th>
                            <th class="p-3">Estado</th>
                            <th class="p-3">Fondo inicial</th>
                            <th class="p-3">Apertura</th>
                            <th class="p-3">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($sesiones as $sesion)
                            <tr class="border-b">
                                <td class="p-3">#{{ $sesion->id }}</td>
                                <td class="p-3">{{ $sesion->caja->nombre }}</td>
                                <td class="p-3">{{ ucfirst($sesion->estado) }}</td>
                                <td class="p-3">${{ $sesion->fondo_inicial }}</td>
                                <td class="p-3">
                                    {{ $sesion->fecha_apertura?->copy()->timezone(config('dashboard.zona_horaria', 'America/Mexico_City'))->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="p-3">
                                    <x-ui.button
                                        :href="route('caja.sesion.detalle', $sesion->id)">
                                        Consultar
                                    </x-ui.button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-5 text-center">
                                    No hay sesiones registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $sesiones->links() }}
            </div>
        </x-ui.card>
    </div>
@endsection