<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Admin\FeedbackResource\Pages;
use App\Models\Feedback;
use App\Enums\Roles;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public static function getLabel(): string
    {
        return Auth::user()->role === Roles::Tutee->value ? 'Mes Feedbacks' : 'Feedbacks';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('tutee_id')
                    ->default(Auth::id()),
                Forms\Components\TextInput::make('text')
                    ->required()
                    ->label('Donnez-nous votre avis !'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('text')
                    ->label('')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => Auth::id() === $record->tutee_id),
            ])
            ->bulkActions([
                //
            ])            
            ->modifyQueryUsing(fn ($query) => $query->when(Auth::user()->role === Roles::Tutee->value, fn ($query) => $query->where('tutee_id', Auth::id()))
        )
        ->defaultSort('created_at', 'desc')
        ->recordUrl(null);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canCreate(): bool
    {
        return Auth::user()->role !== Roles::Administrator->value;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedback::route('/'),
            'create' => Pages\CreateFeedback::route('/create'),
            'edit' => Pages\EditFeedback::route('/{record}/edit'),
        ];
    }
}