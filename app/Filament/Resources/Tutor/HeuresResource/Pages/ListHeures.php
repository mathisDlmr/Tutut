<?php

namespace App\Filament\Resources\Tutor\HeuresResource\Pages;

use App\Filament\Resources\HeuresResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHeures extends ListRecords
{
    protected static string $resource = HeuresResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
