<?php

namespace App\Filament\Resources\Tutor;

use App\Filament\Resources\Tutor\FeedbackResource\Pages;
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
    protected static ?int $navigationSort = 2;

    public static function getLabel(): string
    {
        $user = Auth::user();
        return ($user && Auth::user()->role === Roles::Tutee->value) ? 'Mes Feedbacks' : 'Feedbacks';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('tutee_id')
                    ->default(Auth::id()),
                Forms\Components\Textarea::make('text')
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
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => Auth::id() === $record->tutee_id),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => Auth::id() === $record->tutee_id)
                    ->action(function (Feedback $record) {
                        $record->delete();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Supprimer le feedback')
                    ->modalSubheading('Êtes-vous sûr de vouloir supprimer ce feedback ?')
                    ->modalButton('Supprimer'),
            ])
            ->bulkActions([
                //
            ])            
            ->modifyQueryUsing(fn ($query) => $query->when(Auth::user()->role === Roles::Tutee->value, fn ($query) => $query->where('tutee_id', Auth::id()))
        )
        ->paginated(false)
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