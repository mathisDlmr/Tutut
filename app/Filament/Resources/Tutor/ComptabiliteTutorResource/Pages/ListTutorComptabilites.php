<?php

namespace App\Filament\Resources\Tutor\ComptabiliteTutorResource\Pages;

use App\Filament\Resources\Tutor\ComptabiliteTutorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTutorComptabilites extends ListRecords
{
    protected static string $resource = ComptabiliteTutorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Confirmer les heures')
        ];
    }
}
