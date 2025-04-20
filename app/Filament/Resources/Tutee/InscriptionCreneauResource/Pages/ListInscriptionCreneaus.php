<?php

namespace App\Filament\Resources\Tutee\InscriptionCreneauResource\Pages;

use App\Filament\Resources\Tutee\InscriptionCreneauResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInscriptionCreneaus extends ListRecords
{
    protected static string $resource = InscriptionCreneauResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
