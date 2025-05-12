<?php

namespace App\Filament\Resources\Tutor;

use App\Enums\Roles;
use App\Models\BecomeTutor;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TutorApplicationResource extends Resource
{
    protected static ?string $model = BecomeTutor::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $label = 'Candidatures Tuteur.ice';
    protected static ?string $pluralModelLabel = 'Candidatures Tuteur.ice';
    protected static ?string $navigationGroup = 'Gestion';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::EmployedPrivilegedTutor->value
            || Auth::user()->role === Roles::EmployedTutor->value
            || Auth::user()->role === Roles::Administrator->value);
    }   
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.firstName')
                    ->label('Prénom')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.lastName')
                    ->label('Nom')
                    ->searchable(),
                Tables\Columns\TextColumn::make('semester')
                    ->label('Semestre')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'pending' => 'En attente',
                        'rejected' => 'Refusé',
                    })
                    ->color(fn($state) => match($state) {
                        'pending' => 'warning',
                        'rejected' => 'danger',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('semester')
                    ->label('Semestre')
                    ->options(function () {
                        return BecomeTutor::select('semester')
                            ->where('status', 'pending')
                            ->distinct()
                            ->pluck('semester', 'semester');
                    }),
                Tables\Filters\Filter::make('pending')
                    ->label('En attente uniquement')
                    ->query(fn (Builder $query) => $query->where('status', 'pending'))
                    ->default(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Plus de détails')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Détails de la candidature')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalContent(function (BecomeTutor $record) {
                        return Infolist::make()
                            ->record($record)
                            ->schema([
                                Components\Section::make('Informations Personnelles')
                                    ->schema([
                                        Components\Grid::make(3)
                                            ->schema([
                                                Components\TextEntry::make('user')
                                                    ->label('Nom')
                                                    ->formatStateUsing(fn ($state) => $state['firstName'].' '.$state['lastName']),
                                                Components\TextEntry::make('user.email')
                                                    ->label('Email'),
                                                Components\TextEntry::make('semester')
                                                    ->label('Semestre'),
                                            ]),
                                    ]),
                                Components\Section::make('Matières Proposées')
                                    ->schema([
                                        Components\Grid::make(4)
                                            ->schema(function () use ($record) {
                                                return collect($record->UVs ?? [])
                                                    ->map(fn ($uv) => Components\TextEntry::make($uv['code'])
                                                        ->label(false)
                                                        ->default($uv['code'].' - '.$uv['intitule']))
                                                    ->toArray();
                                            }),
                                    ]),
                                Components\Section::make('Motivation')
                                    ->schema([
                                        Components\TextEntry::make('motivation')
                                            ->label(false),
                                    ]),
                            ]);
                    })
                    ->modalActions(function (BecomeTutor $record) {
                        return $record->status == 'pending' 
                        ? [
                            Action::make('accept')
                                ->label('Accepter')
                                ->color('success')
                                ->icon('heroicon-o-check-circle')
                                ->action(function (BecomeTutor $record) {
                                    $user = $record->user;
                                    $user->role = Roles::Tutor;
                                    $user->save();
                                    $record->delete();
                    
                                    Notification::make()
                                        ->title('Candidature acceptée')
                                        ->success()
                                        ->send();
                                })
                                ->requiresConfirmation()
                                ->modalHeading('Confirmer l\'acceptation')
                                ->modalDescription('Êtes-vous sûr de vouloir accepter cette candidature ?'),
                            
                            Action::make('reject')
                                ->label('Refuser')
                                ->color('danger')
                                ->icon('heroicon-o-x-circle')
                                ->action(function (BecomeTutor $record) {
                                    $record->update(['status' => 'rejected']);
                    
                                    Notification::make()
                                        ->title('Candidature refusée')
                                        ->danger()
                                        ->send();
                                })
                                ->requiresConfirmation()
                                ->modalHeading('Confirmer le refus')
                                ->modalDescription('Êtes-vous sûr de vouloir refuser cette candidature ?'),
                        ] 
                        : [];
                    }),
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
            'index' => TutorApplicationResource\Pages\ListTutorApplications::route('/'),
        ];
    }
}