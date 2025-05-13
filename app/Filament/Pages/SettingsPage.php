<?php

namespace App\Filament\Pages;

use App\Models\UV;
use App\Enums\Roles;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Facades\Http;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Notifications\Notification;

class SettingsPage extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static string $view = 'filament.pages.settings-page';
    protected static ?string $title = 'Settings';
    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::Administrator->value
            || Auth::user()->role === Roles::EmployedPrivilegedTutor->value);
    }  

    public function mount(): void
    {
        // Optionnel : autorisation ou autres initialisations
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(UV::query())
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('intitule')->label('Intitulé')->searchable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public function resetUvs(): void
    {
        $response = Http::withHeaders([
            'x-api-key' => env('API_UTCRAWL_KEY'),
        ])->get(env('API_UTCRAWL'));
    
        if (!$response->ok()) {
            Notification::make()
                ->title('Échec de la récupération des UVs')
                ->danger()
                ->send();
            return;
        }
    
        $data = $response->json();
    
        UV::doesntHave('tutors')
            ->delete();
    
        foreach ($data as $code => $info) {
            if (!isset($info['Titre'])) {
                continue;
            }
        
            $titre = mb_convert_case(mb_strtolower($info['Titre'], 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        
            UV::firstOrCreate(
                ['code' => $code],
                ['intitule' => $titre]
            );
        }            
    
        Notification::make()
            ->title('UVs mises à jour avec succès')
            ->success()
            ->send();
    }    

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset_uvs')
                ->label('Reset les UVs')
                ->action(fn () => $this->resetUVs())
                ->color('danger')
                ->requiresConfirmation()
                ->icon('heroicon-o-arrow-path'),
        ];
    }
}
