<?php

namespace App\Filament\Resources\Admin\SalleResource\Pages;

use App\Filament\Resources\Admin\SalleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalles extends ListRecords
{
    protected static string $resource = SalleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
