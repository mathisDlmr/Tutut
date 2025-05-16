<x-filament::page>
    {{ $this->form }}
    
    <div class="mt-6 flex flex-wrap gap-4">
        <x-filament::button
            wire:click="previewEmail"
            icon="heroicon-o-eye"
            color="gray"
            class="transition-opacity"
            :class="!$canPreviewEmailFlag ? 'opacity-50 cursor-not-allowed' : ''"
            :disabled="!$canPreviewEmailFlag"
        >
            {{ __('pages.send_email.preview') }}
        </x-filament::button>
        
        <x-filament::button
            wire:click="sendEmail"
            icon="heroicon-o-paper-airplane"
            color="primary"
            class="transition-opacity"
            :class="!$canSendEmailFlag ? 'opacity-50 cursor-not-allowed' : ''"
            :disabled="!$canSendEmailFlag"
        >
            {{ __('pages.send_email.send') }}
        </x-filament::button>
        
        <x-filament::button
            wire:click="saveTemplate"
            icon="heroicon-o-bookmark"
            color="primary"
            class="transition-opacity"
            :class="!$canSaveTemplateFlag ? 'opacity-50 cursor-not-allowed' : ''"
            :disabled="!$canSaveTemplateFlag"
        >
            {{ __('pages.send_email.save_template') }}
        </x-filament::button>
        
        @if ($template)
            <x-filament::button
                wire:click="deleteTemplate"
                icon="heroicon-o-trash"
                color="danger"
            >
                {{ __('pages.send_email.delete_template') }}
            </x-filament::button>
        @endif
    </div>
    
    <x-filament::modal id="email-preview" width="4xl">
        <div class="prose dark:prose-invert max-w-full">
            {!! $content !!}
        </div>
        <x-slot name="footer">
            <x-filament::button
                color="gray"
                wire:click="$dispatch('close-modal', { id: 'email-preview' })"
            >
                {{ __('pages.send_email.close') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament::page>