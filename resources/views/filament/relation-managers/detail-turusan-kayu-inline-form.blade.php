<div>
    <form wire:submit.prevent="create">
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-2">
            Simpan
        </x-filament::button>
    </form>

    <div class="mt-4">
        {{ $this->table }}
    </div>
</div>
