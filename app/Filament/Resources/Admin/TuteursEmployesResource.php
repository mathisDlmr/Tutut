<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\TuteursEmployesResource\Pages;
use App\Models\User;
use App\Enums\Roles;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\MultiSelectFilter;

class TuteursEmployesResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && Auth::user()->role === Roles::Administrator->value;
    }     

    public static function getLabel(): string
    {
        return __('resources.tuteurs_employes.label');
    }

    public static function getPluralLabel(): string
    {
        return __('resources.tuteurs_employes.plural_label');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('email')
                    ->label(__('resources.tuteurs_employes.fields.email'))
                    ->required()
                    ->email()
                    ->unique(ignoreRecord: true),
                Select::make('role')
                    ->label(__('resources.tuteurs_employes.fields.role'))
                    ->options([
                        Roles::EmployedTutor->value => __('resources.tuteurs_employes.roles.employed_tutor'),
                        Roles::EmployedPrivilegedTutor->value => __('resources.tuteurs_employes.roles.employed_privileged_tutor'),
                        Roles::Tutor->value => __('resources.tuteurs_employes.roles.tutor'),
                        Roles::Tutee->value => __('resources.tuteurs_employes.roles.tutee'),
                    ])
                    ->default(Roles::EmployedTutor->value)
                    ->required(),
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
                TextColumn::make('role')
                    ->label('Rôle')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => match($state) {
                        Roles::Administrator->value => 'Administrateur',
                        Roles::EmployedTutor->value => 'Tuteur Employé',
                        Roles::EmployedPrivilegedTutor->value => 'Tuteur Employé Privilégié',
                        Roles::Tutor->value => 'Tuteur',
                        Roles::Tutee->value => 'Tutoré',
                        default => 'Inconnu',
                    }),
            ])            
            ->filters([
                MultiSelectFilter::make('role')
                    ->label('Rôle')
                    ->options([
                        Roles::Administrator->value => 'Administrateur',
                        Roles::EmployedTutor->value => 'Tuteur Employé',
                        Roles::EmployedPrivilegedTutor->value => 'Tuteur Employé Privilégié',
                        Roles::Tutor->value => 'Tuteur',
                        Roles::Tutee->value => 'Tutoré',
                    ])
                    ->default([
                        Roles::EmployedTutor->value,
                        Roles::EmployedPrivilegedTutor->value,
                    ])
            ])            
            ->actions([
                Tables\Actions\EditAction::make()
                    ->color('info'),
                Tables\Actions\DeleteAction::make()
                    ->label('Supprimer les droits')
                    ->action(fn (User $record) => $record->update(['role' => Roles::Tutee->value])),
                Tables\Actions\Action::make('upgrade')
                    ->label('Améliorer')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->action(fn (User $record) => $record->update(['role' => Roles::EmployedPrivilegedTutor->value]))
                    ->visible(fn (User $record) => $record->role === Roles::EmployedTutor->value),
                Tables\Actions\Action::make('downgrade')
                    ->label('Rétrograder')
                    ->icon('heroicon-o-user-minus')
                    ->color('warning')
                    ->action(fn (User $record) => $record->update(['role' => Roles::EmployedTutor->value]))
                    ->visible(fn (User $record) => $record->role === Roles::EmployedPrivilegedTutor->value),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Supprimer les droits')
                    ->action(fn (User $record) => $record->update(['role' => Roles::Tutee->value])),
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