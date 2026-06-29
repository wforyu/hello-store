<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="fi-form-actions mt-6">
            <div class="flex flex-row-reverse flex-wrap items-center gap-3">
                <x-filament::button type="submit" color="primary" size="lg">
                    Simpan Pengaturan
                </x-filament::button>
            </div>
        </div>
    </form>
</x-filament-panels::page>
