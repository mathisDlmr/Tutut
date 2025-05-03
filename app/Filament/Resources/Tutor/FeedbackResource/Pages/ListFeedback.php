<?php

namespace App\Filament\Resources\Tutor\FeedbackResource\Pages;

use App\Filament\Resources\Tutor\FeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Enums\Roles;
use Illuminate\Support\Facades\Auth;

class ListFeedback extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    public function getTitle(): string
    {
        return 'Donnez-nous votre avis !';
    }

    protected function getHeaderActions(): array
    {
        if(Auth::user()->role === Roles::Tutee->value){
            return [
                Actions\CreateAction::make()
            ];
        } else {
            return [];
        }
    }
}
