<?php

namespace App\Filament\Resources\Admin\SemaineResource\Pages;

use App\Filament\Resources\SemaineResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSemaine extends EditRecord
{
    protected static string $resource = SemaineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
