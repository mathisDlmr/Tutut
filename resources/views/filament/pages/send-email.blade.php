<x-filament::page>
    {{ $this->form }}

    {{-- Boutons d'action --}}
    <div class="mt-6 flex flex-wrap gap-4">
        <x-filament::button
            wire:click="previewEmail"
            icon="heroicon-o-eye"
            color="gray"
        >
            Aperçu du mail
        </x-filament::button>

        <x-filament::button
            wire:click="sendEmail"
            icon="heroicon-o-paper-airplane"
            color="primary"
        >
            Envoyer le mail
        </x-filament::button>

        <x-filament::button
            wire:click="saveTemplate"
            icon="heroicon-o-bookmark"
            color="primary"
        >
            Enregistrer comme template
        </x-filament::button>

        @if ($template)
            <x-filament::button
                wire:click="deleteTemplate"
                icon="heroicon-o-trash"
                color="danger"
            >
                Supprimer le template
            </x-filament::button>
        @endif
    </div>

    {{-- Modal de prévisualisation --}}
    <x-filament::modal id="email-preview" width="4xl">
        <div class="prose dark:prose-invert max-w-full">
            {!! $content !!}
        </div>

        <x-slot name="footer">
            <x-filament::button
                color="gray"
                wire:click="$dispatch('close-modal', { id: 'email-preview' })"
            >
                Fermer
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament::page>
