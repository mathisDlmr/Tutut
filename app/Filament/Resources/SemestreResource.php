<?php

namespace App\Filament\Resources;

use App\Models\Semestre;
use Filament\Forms;
use Filament\Tables;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\DatePicker;
use App\Filament\Resources\Admin\SemestreResource\Pages;

class SemestreResource extends Resource
{
    protected static ?string $model = Semestre::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(3)
                        ->placeholder('A25')
                        ->columnSpan(1),

                    Forms\Components\DatePicker::make('debut')
                        ->required()
                        ->columnSpan(1)
                        ->reactive()

                        ->afterStateUpdated(function (callable $set, $state, $get) {
                            $fin = $get('fin');
                            if ($fin && $state >= $fin) {
                                $set('fin', null);
                            }
                        }),

                    Forms\Components\DatePicker::make('fin')
                        ->required()
                        ->columnSpan(1)
                        ->reactive()

                        ->afterStateUpdated(function (callable $set, $state, $get) {
                            $debut = $get('debut');
                            if ($debut && $state <= $debut) {
                                $set('fin', null);
                            }
                        }),
                ]),

            Forms\Components\Grid::make(2)
                ->schema([
                    DatePicker::make('debut_medians'),
                    DatePicker::make('fin_medians'),
                    DatePicker::make('debut_finaux'),
                    DatePicker::make('fin_finaux'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable(),                    
                TextColumn::make('is_active')
                    ->label('Actif')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Oui' : 'Non')
                    ->badge()
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),  
                TextColumn::make('debut')
                    ->label('Date de début')
                    ->formatStateUsing(fn (string $state) => Carbon::parse($state)->locale('fr')->translatedFormat('l d F Y')),
                TextColumn::make('fin')
                    ->label('Date de fin')
                    ->formatStateUsing(fn (string $state) => Carbon::parse($state)->locale('fr')->translatedFormat('l d F Y')),
            ])
            ->defaultSort('fin', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('Activer')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->color('success')
                    ->visible(fn ($record) => !$record->is_active)
                    ->action(function (Semestre $record) {
                        Semestre::setActive($record);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSemestres::route('/'),
            'create' => Pages\CreateSemestre::route('/create'),
            'edit' => Pages\EditSemestre::route('/{record}/edit'),
        ];
    }
}