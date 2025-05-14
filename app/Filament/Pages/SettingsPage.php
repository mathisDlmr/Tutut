<?php
namespace App\Filament\Pages;

use App\Models\UV;
use App\Enums\Roles;
use Filament\Actions\Action;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Support\Facades\Auth;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Facades\Http;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Filament\Tables\Actions\Action as ActionsAction;

class SettingsPage extends Page implements Tables\Contracts\HasTable, Forms\Contracts\HasForms
{
    use Tables\Concerns\InteractsWithTable;
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static string $view = 'filament.pages.settings-page';
    protected static ?string $title = 'Settings';
    protected static ?int $navigationSort = 4;

    public $employedTutorRegistrationDay;
    public $employedTutorRegistrationTime;
    public $tutorRegistrationDay;
    public $tutorRegistrationTime;
    public $tuteeRegistrationDay;
    public $tuteeRegistrationTime;
    public $minTimeCancellationDay;
    public $minTimeCancellationTime;
    public $useOneDayBeforeCancellation = false;

    protected $settings = [
        'employedTutorRegistrationDay' => null,
        'employedTutorRegistrationTime' => null,
        'tutorRegistrationDay' => null,
        'tutorRegistrationTime' => null,
        'tuteeRegistrationDay' => null,
        'tuteeRegistrationTime' => null,
        'minTimeCancellationDay' => null,
        'minTimeCancellationTime' => null,
        'useOneDayBeforeCancellation' => false,
    ];
    
    protected $days = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ];

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::Administrator->value
            || Auth::user()->role === Roles::EmployedPrivilegedTutor->value);
    }

    public function mount(): void
    {
        $this->loadSettings();        
        $this->form->fill($this->settings);
    }

    protected function loadSettings(): void
    {
        if (Storage::exists('settings.json')) {
            $this->settings = json_decode(Storage::get('settings.json'), true) ?: $this->settings;
        }
    }

    public function saveSettings(): void
    {
        $data = $this->form->getState();
        
        foreach ($data as $key => $value) {
            $this->settings[$key] = $value;
        }
        
        // Si on utilise "la veille", on vide les champs minTimeCancellation
        if ($data['useOneDayBeforeCancellation']) {
            $this->settings['minTimeCancellationDay'] = null;
            $this->settings['minTimeCancellationTime'] = null;
        }
        
        Storage::put('settings.json', json_encode($this->settings));
        
        Notification::make()
            ->title('Paramètres sauvegardés avec succès')
            ->success()
            ->send();
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Paramètres')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Section::make("Date d'ouverture des inscriptions pour les tuteur.ice.s employé.e.s")
                                ->schema([
                                    Select::make('employedTutorRegistrationDay')
                                        ->label('Jour')
                                        ->options($this->days)
                                        ->required(),
                                    TimePicker::make('employedTutorRegistrationTime')
                                        ->label('Heure')
                                        ->seconds(false)
                                        ->required(),
                                ])
                                ->columnSpan(1)
                                ->compact(),

                            Section::make("Date d'ouverture des inscriptions pour les tuteur.ice.s bénévoles")
                                ->schema([
                                    Select::make('tutorRegistrationDay')
                                        ->label('Jour')
                                        ->options($this->days)
                                        ->required(),
                                    TimePicker::make('tutorRegistrationTime')
                                        ->label('Heure')
                                        ->seconds(false)
                                        ->required(),
                                ])
                                ->columnSpan(1)
                                ->compact(),

                            Section::make("Date d'ouverture des inscriptions pour les tutoré.e.s")
                                ->schema([
                                    Select::make('tuteeRegistrationDay')
                                        ->label('Jour')
                                        ->options($this->days)
                                        ->required(),
                                    TimePicker::make('tuteeRegistrationTime')
                                        ->label('Heure')
                                        ->seconds(false)
                                        ->required(),
                                ])
                                ->columnSpan(1)
                                ->compact(),
                            Section::make("Délai d'annulation des créneaux")
                                ->schema([
                                    Toggle::make('useOneDayBeforeCancellation')
                                        ->label('Limiter à "la veille" uniquement')
                                        ->reactive()
                                        ->inline(false)
                                        ->columnSpan('full'),
                                        
                                    Forms\Components\Grid::make()
                                        ->schema([
                                            Select::make('minTimeCancellationDay')
                                                ->label('Jour')
                                                ->options($this->days)
                                                ->required(),
                                            TimePicker::make('minTimeCancellationTime')
                                                ->label('Heure')
                                                ->seconds(false)
                                                ->required(),
                                        ])
                                        ->columnSpan('full')
                                        ->columns(2)
                                        ->hidden(fn (callable $get) => $get('useOneDayBeforeCancellation')),
                                ])
                                ->columnSpan(1)
                                ->compact(),
                        ]),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('save')
                            ->label('Enregistrer')
                            ->action('saveSettings')
                            ->color('primary'),
                    ])
                ])
        ];
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(UV::query())
            ->heading('Catalogue des UVs')
            ->headerActions([
                TableAction::make('reset_uvs')
                    ->label('Reset les UVs')
                    ->action(fn () => $this->resetUvs())
                    ->color('danger')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-arrow-path'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('intitule')->label('Intitulé')->searchable(),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Modifier une UV')
                    ->form([
                        Forms\Components\TextInput::make('code')->label('UV')->required(),
                        Forms\Components\TextInput::make('intitule')->label('Intitulé')->required(),
                    ]),
                DeleteAction::make(),
            ]);
    }

    public function resetUvs(): void
    {
        $response = Http::withHeaders([
            'x-api-key' => env('API_UTCRAWL_KEY'),
        ])->get(env('API_UTCRAWL'));

        if (!$response->ok()) {
            Notification::make()
                ->title('Échec de la récupération des UVs')
                ->danger()
                ->send();
            return;
        }

        $data = $response->json();
        UV::doesntHave('tutors')
            ->delete();

        foreach ($data as $code => $info) {
            if (!isset($info['Titre'])) {
                continue;
            }
            $titre = mb_convert_case(mb_strtolower($info['Titre'], 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            UV::firstOrCreate(
                ['code' => $code],
                ['intitule' => $titre]
            );
        }

        Notification::make()
            ->title('UVs mises à jour avec succès')
            ->success()
            ->send();
    }
}