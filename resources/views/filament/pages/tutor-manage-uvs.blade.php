<x-filament::page>
    <form wire:submit.prevent="createUv" class="space-y-4">
        {{ $this->form }}
        <x-filament::button 
            type="submit"
            class="transition-opacity"
            :class="!$canSaveUv ? 'opacity-50 cursor-not-allowed' : ''"
            :disabled="!$canSaveUv">
            Ajouter
        </x-filament::button>
    </form>
    
    <hr class="my-6" />
    
    <form wire:submit.prevent="updateLanguages" class="space-y-4">
        {{ $this->languagesFormComponent }}
        <x-filament::button type="submit">
            Mettre à jour mes langues
        </x-filament::button>
    </form>
    
    <hr class="my-6" />
    
    {{ $this->table }}
</x-filament::page>