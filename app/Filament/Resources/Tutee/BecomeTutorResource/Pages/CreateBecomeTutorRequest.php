<?php

namespace App\Filament\Resources\Tutee\BecomeTutorResource\Pages;

use App\Models\UV;
use App\Models\BecomeTutor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\Tutee\BecomeTutorResource;
use Illuminate\Contracts\Support\Htmlable;

class CreateBecomeTutorRequest extends CreateRecord
{
    protected static string $resource = BecomeTutorResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('resources.become_tutor.title');
    }
    
    protected function hasCreateAction(): bool
    {
        return false;
    }
    
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('resources.become_tutor.actions.save'))
                ->submit('create')
                ->color('primary'),
            Action::make('delete')
                ->label(__('resources.become_tutor.actions.delete'))
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('resources.become_tutor.actions.delete_modal_title'))
                ->modalDescription(__('resources.become_tutor.actions.delete_modal_description'))
                ->action(function () {
                    $existingRequest = Auth::user()->becomeTutorRequest;
                    if ($existingRequest) {
                        $existingRequest->delete();
                        
                        Notification::make()
                            ->title(__('resources.become_tutor.notifications.deleted_title'))
                            ->body(__('resources.become_tutor.notifications.deleted_body'))
                            ->danger()
                            ->send();     
                        
                        $this->form->fill();
                    }
                })
                ->visible(fn() => (bool) Auth::user()->becomeTutorRequest)
        ];
    }
    
    public function mount(): void
    {
        $existingRequest = Auth::user()->becomeTutorRequest;
        
        if ($existingRequest) {
            $this->form->fill([
                'fk_user' => $existingRequest->fk_user,
                'semester' => $existingRequest->semester,
                'motivation' => $existingRequest->motivation,
                'UVs' => collect($existingRequest->UVs)->pluck('code')->toArray(),
                'status' => $existingRequest->status
            ]);
        } else {
            parent::mount();
        }
    }
    
    protected function afterCreate(): void
    {
        Notification::make()
            ->title(__('resources.become_tutor.notifications.submitted_title'))
            ->body(__('resources.become_tutor.notifications.submitted_body'))
            ->success()
            ->send();
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['UVs'])) {
            $uvList = [];
            foreach ($data['UVs'] as $uvCode) {
                $uv = UV::where('code', $uvCode)->first();
                if ($uv) {
                    $uvList[] = [
                        'code' => $uv->code,
                        'intitule' => $uv->intitule
                    ];
                }
            }
            $data['UVs'] = $uvList;
        }
        $data['status'] = 'pending';
        $data['fk_user'] = Auth::id();
        return $data;
    }
    
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $existingRequest = BecomeTutor::where('fk_user', Auth::id())->first();
        
        if ($existingRequest) {
            $existingRequest->update($data);
            return $existingRequest;
        }
        
        return static::getModel()::create($data);
    }
}