<?php

namespace App\Filament\Resources\Admin\ComptabiliteHeuresResource\Pages;

use App\Filament\Resources\ComptabiliteHeuresResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListComptabiliteHeures extends ListRecords
{
    protected static string $resource = ComptabiliteHeuresResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
