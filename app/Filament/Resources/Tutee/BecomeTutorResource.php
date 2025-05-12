<?php
namespace App\Filament\Resources\Tutee;

use App\Enums\Roles;
use App\Models\UV;
use App\Models\BecomeTutor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\Tutee\BecomeTutorResource\Pages\CreateBecomeTutorRequest;

class BecomeTutorResource extends Resource
{
    protected static ?string $model = BecomeTutor::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Devenir Tuteur.ice';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && Auth::user()->role === Roles::Tutee->value;
    }  
    
    public static function form(Form $form): Form
    {
        $currentUser = Auth::user();
        $existingRequest = $currentUser->becomeTutorRequest;
        
        return $form
            ->schema([
                Forms\Components\View::make('filament.components.refused.tutor-rejected')
                    ->visible($existingRequest && $existingRequest->status === 'rejected'),
                Forms\Components\Section::make('Candidature Tuteur.ice')
                    ->description('Remplissez ce formulaire pour devenir tuteur.ice')
                    ->schema([
                        Forms\Components\TextInput::make('user_firstName')
                            ->label('Prénom')
                            ->default($currentUser->firstName)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('user_lastName')
                            ->label('Nom')
                            ->default($currentUser->lastName)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('user_email')
                            ->label('Email')
                            ->email()
                            ->default($currentUser->email)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Hidden::make('fk_user')
                            ->default($currentUser->id),
                        Forms\Components\TextInput::make('semester')
                            ->label('Semestre (ex: TC03)')
                            ->required()
                            ->maxLength(4)
                            ->helperText('Format: XXNN (ex: TC03, GI02)')
                            ->regex('/^[A-Za-z]{2}[0-9]{2}$/'),
                        Forms\Components\Textarea::make('motivation')
                            ->label('Motivation')
                            ->required()
                            ->rows(5)
                            ->placeholder('Décrivez pourquoi vous souhaitez devenir tuteur.ice et quelles sont vos expériences pertinentes'),
                        Forms\Components\Hidden::make('status')
                            ->default('pending'),
                        Forms\Components\Select::make('UVs')
                            ->label('Matières validées')
                            ->options(UV::all()->pluck('code', 'code'))
                            ->multiple()
                            ->searchable()
                            ->required()
                            ->helperText('Sélectionnez les matières que vous pouvez enseigner'),
                    ]),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => CreateBecomeTutorRequest::route('/'),
        ];
    }
}