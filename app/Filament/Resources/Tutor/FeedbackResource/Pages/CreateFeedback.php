<?php

namespace App\Filament\Resources\Tutor\FeedbackResource\Pages;

use App\Filament\Resources\Tutor\FeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFeedback extends CreateRecord
{
    protected static string $resource = FeedbackResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function hasCreateAnotherButton(): bool
    {
        return false;
    }
}
