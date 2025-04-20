<?php

namespace App\Filament\Resources\Admin\TuteursEmployesResource\Pages;

use App\Filament\Resources\Admin\TuteursEmployesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTuteursEmployes extends ListRecords
{
    protected static string $resource = TuteursEmployesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
