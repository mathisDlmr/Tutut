<?php

namespace App\Filament\Resources\Tutee\InscriptionCreneauResource\Pages;

use App\Filament\Resources\Tutee\InscriptionCreneauResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInscriptionCreneau extends EditRecord
{
    protected static string $resource = InscriptionCreneauResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
