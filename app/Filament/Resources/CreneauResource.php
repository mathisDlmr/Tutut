<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Tutor\CreneauResource\Pages;
use App\Filament\Resources\Tutor\CreneauResource\RelationManagers;
use App\Models\Creneaux;
use App\Models\Semaine;
use App\Models\Salle;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;

class CreneauResource extends Resource
{
    protected static ?string $model = Creneaux::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Shotgun Créneaux';
    protected static ?string $pluralModelLabel = 'Créneaux';

    public static function form(Form $form): Form
    {
        return $form->schema([
            //
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ShotgunCreneaux::route('/'),
        ];
    }
}