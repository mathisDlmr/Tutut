<x-filament::page>
    <form wire:submit.prevent="createUv" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit">Ajouter cette UV</x-filament::button>
    </form>

    <hr class="my-6" />

    {{ $this->table }}
</x-filament::page>