<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\UV;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use App\Enums\Roles;
use App\Models\User;
use Filament\Notifications\Notification;

class TutorManageUvs extends Page implements Forms\Contracts\HasForms, Tables\Contracts\HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static string $view = 'filament.pages.tutor-manage-uvs';
    protected static ?string $title = 'Mes UVs proposées';
    protected static ?string $navigationGroup = 'Tutorat';
    protected static ?int $navigationSort = 3;

    public array $languagesForm = [];
    public $selected_codes;
    public $code;
    public $intitule;
    
    // Propriété réactive pour l'état du bouton
    public $canSaveUv = false;

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::EmployedPrivilegedTutor->value
            || Auth::user()->role === Roles::EmployedTutor->value
            || Auth::user()->role === Roles::Tutor->value);
    }    

    public function getLanguagesFormComponentProperty(): Form
    {
        return $this->makeForm()
            ->schema([
                Forms\Components\CheckboxList::make('languages')
                    ->label('Langues parlées')
                    ->options([
                        'en' => '🇬🇧 Anglais',
                        'es' => '🇪🇸 Espagnol',
                        'zh' => '🇨🇳 Chinois',
                        'de' => '🇩🇪 Allemand',
                        'ar' => '🇸🇦 Arabe',
                        'ru' => '🇷🇺 Russe',
                        'ja' => '🇯🇵 Japonais',
                        'it' => '🇮🇹 Italien',
                    ])
                    ->columns(2)
            ])
            ->statePath('languagesForm');
    }     
    
    public function mount(): void
    {
        $this->languagesForm = [
            'languages' => Auth::user()->languages ?? [],
        ];
        $this->form->fill([
            'languages' => $this->languagesForm['languages'],
        ]);
        
        $this->updateCanSaveUv();
    }       

    public function formLanguagesForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\CheckboxList::make('languages')
                    ->label('Langues parlées')
                    ->options([
                        'en' => '🇬🇧 Anglais',
                        'es' => '🇪🇸 Espagnol',
                        'zh' => '🇨🇳 Chinois',
                        'de' => '🇩🇪 Allemand',
                        'ar' => '🇸🇦 Arabe',
                        'ru' => '🇷🇺 Russe',
                        'ja' => '🇯🇵 Japonais',
                        'it' => '🇮🇹 Italien',
                    ])
                    ->columns(2)
                    ->default(Auth::user()->languages ?? [])
                    ->reactive()
            ])
            ->statePath('languagesForm');
    }
    
    public function updateLanguages(): void
    {
        $data = $this->languagesFormComponent->getState();
        Auth::user()->update([
            'languages' => $data['languages'] ?? [],
        ]);
    
        Notification::make()
            ->title('Langues mises à jour')
            ->success()
            ->body('Vos langues ont été mises à jour avec succès.')
            ->send();
    }      

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Proposer une UV')
                ->description("Si vous ne trouvez pas l'UV que vous cherchez, vous pouvez demander à " . 
                    User::where('role', Roles::EmployedPrivilegedTutor->value)
                    ->get()
                    ->map(fn ($user) => "{$user->firstName} {$user->lastName}")
                    ->join(' ou ')
                . ' de l\'ajouter')
                ->schema([
                    Forms\Components\Select::make('selected_codes')
                        ->label('UV existante')
                        ->options(
                            \App\Models\UV::whereNotIn('code', Auth::user()->proposedUvs()->pluck('code'))
                            ->get()
                            ->mapWithKeys(fn ($uv) => [$uv->code => "{$uv->code} - {$uv->intitule}"])
                        )
                        ->searchable()
                        ->multiple()
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->updateCanSaveUv())
                        ->requiredWithout(['code', 'intitule']),
                ]),
        
            Forms\Components\Section::make('OU créer une nouvelle UV')
                ->description('Créez une nouvelle UV avec son code et son intitulé')
                ->schema([
                    Forms\Components\TextInput::make('code')
                    ->label("Code de l'UV")
                    ->maxLength(10)
                    ->reactive()
                    ->afterStateUpdated(fn () => $this->updateCanSaveUv())
                    ->requiredWithout('selected_codes'),
            
                    Forms\Components\TextInput::make('intitule')
                    ->label("Intitulé de l'UV")
                    ->maxLength(255)
                    ->reactive()
                    ->afterStateUpdated(fn () => $this->updateCanSaveUv())
                    ->requiredWithout('selected_codes'),
                ])
                ->columns(2)
                ->visible(fn () => Auth::user()->role === Roles::EmployedPrivilegedTutor->value),
        ])->statePath('');
    }
    
    public function updateCanSaveUv(): void
    {
        $this->canSaveUv = (!empty($this->selected_codes) && is_array($this->selected_codes)) || 
                          (!empty($this->code) && !empty($this->intitule));
    }
    
    public function updated($property): void
    {
        if (in_array($property, ['selected_codes', 'code', 'intitule'])) {
            $this->updateCanSaveUv();
        }
    }            

    public function createUv()
    {
        $data = $this->form->getState();

        if (!empty($data['selected_codes']) && is_array($data['selected_codes'])) {
            Auth::user()->proposedUvs()->syncWithoutDetaching($data['selected_codes']);

            Notification::make()
                ->title('UV(s) ajoutée(s)')
                ->success()
                ->body('Vos UVs ont été mises à jour avec succès.')
                ->send();
        } elseif (!empty($data['code']) && !empty($data['intitule'])) {
            $uv = UV::firstOrCreate(
                ['code' => $data['code']],
                ['intitule' => $data['intitule']]
            );

            Auth::user()->proposedUvs()->syncWithoutDetaching([$uv->code]);

            Notification::make()
                ->title('UV(s) ajoutée(s)')
                ->success()
                ->body("L'UV a été créé et vos UVs ont été mises à jour avec succès.")
                ->send();
        }

        $this->reset(['selected_codes', 'code', 'intitule']);
        $this->form->fill();
        $this->updateCanSaveUv();
    }    

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Auth::user()->proposedUvs()->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('intitule'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->action(function (UV $record) {
                        Auth::user()->proposedUvs()->detach($record->code);
                    }),
            ]);
    }
}