<?php

namespace App\Filament\Resources\Tutor\ComptabiliteResource\Pages;

use App\Filament\Resources\Tutor\ComptabiliteResource;
use App\Models\Comptabilite;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListComptabilites extends ListRecords
{
    protected static string $resource = ComptabiliteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Confirmer les heures')
        ];
    }
}
