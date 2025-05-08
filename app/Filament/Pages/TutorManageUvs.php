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
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;

class TutorManageUvs extends Page implements Forms\Contracts\HasForms, Tables\Contracts\HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static string $view = 'filament.pages.tutor-manage-uvs';
    protected static ?string $title = 'Mes UVs proposées';
    protected static ?string $navigationGroup = 'Tutorat';

    public array $languagesForm = [];
    public $selected_code;
    public $code;
    public $intitule;

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
            ->description('Si vous ne trouvez pas l’UV que vous cherchez, vous pouvez demander à ' . 
                User::where('role', Roles::EmployedPrivilegedTutor->value)
                ->get()
                ->map(fn ($user) => "{$user->firstName} {$user->lastName}")
                ->join(' ou ')
            . ' de l\'ajouter')
            ->schema([
                Forms\Components\Select::make('selected_code')
                ->label('UV existante')
                ->options(
                    \App\Models\UV::whereNotIn('code', Auth::user()->proposedUvs()->pluck('code'))
                    ->get()
                    ->mapWithKeys(fn ($uv) => [$uv->code => "{$uv->code} - {$uv->intitule}"])
                )
                ->searchable()
                ->reactive()
                ->requiredWithout(['code', 'intitule']),
            ]),
        
            Forms\Components\Section::make('OU créer une nouvelle UV')
            ->description('Créez une nouvelle UV avec son code et son intitulé')
            ->schema([
                Forms\Components\TextInput::make('code')
                ->label('Code de l’UV')
                ->maxLength(10)
                ->requiredWithout('selected_code'),
        
                Forms\Components\TextInput::make('intitule')
                ->label('Intitulé de l’UV')
                ->maxLength(255)
                ->requiredWithout('selected_code'),
            ])
            ->columns(2)
            ->visible(fn () => Auth::user()->role === Roles::EmployedPrivilegedTutor->value),
        ])->statePath('');
    }               

    public function createUv()
    {
        $data = $this->form->getState();
    
        if (!empty($data['selected_code'])) {
            $uv = UV::find($data['selected_code']);
        } else {
            $uv = UV::firstOrCreate(
                ['code' => $data['code']],
                ['intitule' => $data['intitule']]
            );
        }
    
        if ($uv) {
            Auth::user()->proposedUvs()->syncWithoutDetaching([$uv->code]);
            session()->flash('success', 'UV ajoutée à vos matières.');
        }
    
        $this->reset(['selected_code', 'code', 'intitule']);
        $this->form->fill();
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