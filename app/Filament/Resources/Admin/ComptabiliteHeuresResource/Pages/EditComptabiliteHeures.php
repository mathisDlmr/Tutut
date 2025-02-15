<?php

namespace App\Filament\Resources\Admin\ComptabiliteHeuresResource\Pages;

use App\Filament\Resources\ComptabiliteHeuresResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComptabiliteHeures extends EditRecord
{
    protected static string $resource = ComptabiliteHeuresResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
