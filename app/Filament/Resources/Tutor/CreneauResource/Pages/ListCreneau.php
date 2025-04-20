<?php

namespace App\Filament\Resources\Tutor\CreneauResource\Pages;

use App\Filament\Resources\Tutor\CreneauResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class Listcreneau extends ListRecords
{
    protected static string $resource = CreneauResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
