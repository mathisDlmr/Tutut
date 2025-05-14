<x-filament::page>
    {{-- Formulaire UV --}}
    <form wire:submit.prevent="createUv" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit">Ajouter</x-filament::button>
    </form>

    <hr class="my-6" />

    {{-- Formulaire Langues --}}
    <form wire:submit.prevent="updateLanguages" class="space-y-4">
        {{ $this->languagesFormComponent }}
        <x-filament::button type="submit">Mettre à jour mes langues</x-filament::button>
    </form>

    <hr class="my-6" />

    {{ $this->table }}
</x-filament::page>
