<?php

namespace App\Filament\Resources\Admin\TuteursEmployesResource\Pages;

use App\Filament\Resources\Admin\TuteursEmployesResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTuteursEmployes extends CreateRecord
{
    protected static string $resource = TuteursEmployesResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $emails = is_string($data['emails']) ? explode(',', $data['emails']) : $data['emails'];    
        $emails = array_map('trim', $emails);
        $role = $data['role'];
        $createdUsers = collect();
    
        foreach ($emails as $email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'role' => $role,
                ]
            );
    
            $createdUsers->push($user);
        }
    
        Notification::make()
            ->title("Création réussie")
            ->body("{$createdUsers->count()} utilisateur·trice·s ont été ajouté·e·s.")
            ->success()
            ->send();
    
        return $createdUsers->first() ?? new User();
    }
    
}
