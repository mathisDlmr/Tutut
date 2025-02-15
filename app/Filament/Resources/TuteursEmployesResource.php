<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Admin\TuteursEmployesResource\Pages;
use App\Models\EmployedTutorList;
use App\Enums\Roles;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

class TuteursEmployesResource extends Resource
{
    protected static ?string $model = EmployedTutorList::class;
    protected static ?string $label = 'Tuteurs Emploi Etu';
    protected static ?string $pluralLabel = 'Tuteurs Emploi Etu';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && $user->role === Roles::Administrator->value;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('email')
                    ->label('Adresse Email')
                    ->required()
                    ->email()
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Ajouté le')
                    ->date(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTuteursEmployes::route('/'),
            'create' => Pages\CreateTuteursEmployes::route('/create'),
            'edit' => Pages\EditTuteursEmployes::route('/{record}/edit'),
        ];
    }
}