@if (session('status'))
    <x-ui.alert variant="success">
        {{ session('status') }}
    </x-ui.alert>
@endif

@if ($errors->any())
    <x-ui.alert variant="danger" title="Revisa la información">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
