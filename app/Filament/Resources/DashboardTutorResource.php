<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Tutor\DashboardTutorResource\Pages;
use App\Enums\Roles;
use App\Models\Creneaux;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DashboardTutorResource extends Resource
{
    protected static ?string $model = Creneaux::class;
    protected static ?string $label = 'Tableau de Bord';
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && ($user->role === Roles::Tutor->value || $user->role === Roles::EmployedTutor->value);
    }

    public static function table(Table $table): Table
    {
        return $table   
            ->columns([
                Tables\Columns\TextColumn::make('start')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('inscrits')
                    ->counts('inscriptions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('matieres')
                    ->getStateUsing(fn ($record) => $record->inscriptions->pluck('matiere')->implode(', ')),
            ])
            ->filters([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDashboardTutor::route('/'),
        ];
    }
}
