<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Tutor\HeuresResource\Pages;
use App\Models\Creneaux;
USE App\Enums\Roles;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class HeuresResource extends Resource
{
    protected static ?string $model = Creneaux::class;
    protected static ?string $label = 'Heures par Semaine';
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && ($user->role === Roles::Tutor->value || $user->role === Roles::EmployedTutor->value);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('week')
                    ->label('Semaine')
                    ->getStateUsing(fn ($record) => $record->start->format('W')),
                Tables\Columns\TextColumn::make('hours')
                    ->label('Heures')
                    ->getStateUsing(fn ($record) => $record->end->diffInHours($record->start)),
            ])
            ->filters([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHeures::route('/'),
        ];
    }
}
