<?php

namespace App\Filament\Resources\Tutor\DashboardTutorResource\Pages;

use App\Filament\Resources\DashboardTutorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDashboardTutor extends ListRecords
{
    protected static string $resource = DashboardTutorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
