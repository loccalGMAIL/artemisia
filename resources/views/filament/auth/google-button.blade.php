@if (filled(config('services.google.client_id')))
    @php
        $panel = \Filament\Facades\Filament::getCurrentPanel();
    @endphp

    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(0, 0, 0, 0.1);">
        <x-filament::button
            tag="a"
            :href="$panel->route('auth.google.redirect')"
            color="gray"
            :outlined="true"
            style="width: 100%; justify-content: center;"
        >
            Entrar con Google
        </x-filament::button>
    </div>
@endif
