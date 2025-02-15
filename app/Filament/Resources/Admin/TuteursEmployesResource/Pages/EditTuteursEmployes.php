<?php

namespace App\Filament\Resources\Admin\TuteursEmployesResource\Pages;

use App\Filament\Resources\TuteursEmployesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTuteursEmployes extends EditRecord
{
    protected static string $resource = TuteursEmployesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
