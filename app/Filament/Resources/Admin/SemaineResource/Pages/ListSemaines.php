<?php

namespace App\Filament\Resources\Admin\SemaineResource\Pages;

use App\Filament\Resources\Admin\SemaineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSemaines extends ListRecords
{
    protected static string $resource = SemaineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
