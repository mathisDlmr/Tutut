<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Tutor\MatiereResource\Pages;
use App\Models\EnseignementsTutor;
use App\Enums\Roles;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MatiereResource extends Resource
{
    protected static ?string $model = EnseignementsTutor::class;
    protected static ?string $label = 'Mes Matières';
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && ($user->role === Roles::Tutor->value || $user->role === Roles::EmployedTutor->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('tutor_id')
                ->default(Auth::id()),
                Forms\Components\TextInput::make('enseignements')
                    ->label('Matières')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('enseignements')
                    ->label('Matières')
                    ->searchable(),
            ])
            ->filters([
                // 
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatiere::route('/'),
            'edit' => Pages\EditMatiere::route('/{record}/edit'),
        ];
    }
}
