<?php

namespace App\Filament\Resources\Admin\FeedbackResource\Pages;

use App\Filament\Resources\FeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditFeedback extends EditRecord
{
    protected static string $resource = FeedbackResource::class;

    public static function canEdit($record): bool
    {
        return Auth::id() === $record->tutee_id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
