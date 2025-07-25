<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use League\CommonMark\CommonMarkConverter;

class Tutoriel extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static string $view = 'filament.pages.tutoriel';
    protected static ?int $navigationSort = 5;
    
    public string $htmlContent;

    public function getTitle(): string
    {
        return '';
    }

    public static function getNavigationLabel(): string 
    {
        return __('resources.pages.help.title');
    }

    public function mount(): void
    {
        $markdown = File::get(resource_path('markdown/help.md'));
        $converter = new CommonMarkConverter();
        $this->htmlContent = $converter->convertToHtml($markdown);
    }
}
