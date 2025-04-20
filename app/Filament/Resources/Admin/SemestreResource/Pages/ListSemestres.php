<?php

namespace App\Filament\Resources\Admin\SemestreResource\Pages;

use App\Filament\Resources\Admin\SemestreResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSemestres extends ListRecords
{
    protected static string $resource = SemestreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
