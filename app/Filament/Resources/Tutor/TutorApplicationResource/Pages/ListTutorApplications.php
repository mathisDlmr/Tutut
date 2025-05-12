<?php

namespace App\Filament\Resources\Tutor\TutorApplicationResource\Pages;

use App\Filament\Resources\Tutor\TutorApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListTutorApplications extends ListRecords
{
    protected static string $resource = TutorApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 
        ];
    }
}