<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Admin\ComptabiliteHeuresResource\Pages;
use App\Models\User;
use App\Models\Creneaux;
use App\Enums\Roles;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ComptabiliteHeuresResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $label = 'Comptabilité Heures';
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && $user->role === Roles::Administrator->value;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('firstName')->label('Prénom')->sortable(),
                TextColumn::make('lastName')->label('Nom')->sortable(),
                TextColumn::make('email')->label('Email')->sortable()->searchable(),
                TextColumn::make('heures_total')
                    ->label('Heures totales')
                    ->getStateUsing(fn(User $record) => 
                        Creneaux::where('tutor1_id', $record->id)
                            ->orWhere('tutor2_id', $record->id)
                            ->sum(DB::raw("TIMESTAMPDIFF(HOUR, start, end)"))
                    ),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComptabiliteHeures::route('/'),
        ];
    }
}


/*
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListComptabiliteHeures::route('/'),
            'create' => Pages\CreateComptabiliteHeures::route('/create'),
            'edit' => Pages\EditComptabiliteHeures::route('/{record}/edit'),
        ];
    }
*/
