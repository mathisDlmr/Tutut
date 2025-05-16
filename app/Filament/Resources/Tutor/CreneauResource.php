<?php

namespace App\Filament\Resources\Tutor;

use App\Filament\Resources\Tutor\CreneauResource\Pages;
use App\Models\Creneaux;
use App\Models\Semaine;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use App\Enums\Roles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CreneauResource extends Resource
{
    protected static ?string $model = Creneaux::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Shotgun Créneaux';
    protected static ?string $pluralModelLabel = 'Créneaux';
    protected static ?string $navigationGroup = 'Tutorat';
    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('resources.creneau.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.creneau.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.creneau.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('resources.common.navigation.tutorat');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::EmployedPrivilegedTutor->value
            || Auth::user()->role === Roles::EmployedTutor->value
            || Auth::user()->role === Roles::Tutor->value);
    }    

    public static function form(Form $form): Form
    {
        return $form->schema([
            //
        ]);
    }
    
    public function getHoraireCompletAttribute(): string
    {
        return $this->start->format('H:i') . ' - ' . $this->end->format('H:i');
    }

    protected static function getRegistrationSettings(): array
    {
        $settingsPath = Storage::path('settings.json');
        if (file_exists($settingsPath)) {
            $settings = json_decode(file_get_contents($settingsPath), true);
            return $settings;
        }
        
        return [   // Valeurs par défaut si le fichier n'existe pas
            'employedTutorRegistrationDay' => 'monday',
            'employedTutorRegistrationTime' => '16:00',
            'tutorRegistrationDay' => 'friday',
            'tutorRegistrationTime' => '16:00',
        ];
    }
    
    protected static function shouldShowNextWeek(): bool
    {
        $user = Auth::user();
        $settings = self::getRegistrationSettings();
        $now = Carbon::now();
        
        if ($user->role === Roles::Tutor->value) {
            $day = $settings['tutorRegistrationDay'] ?? 'friday';
            $time = $settings['tutorRegistrationTime'] ?? '16:00';
        } else {
            $day = $settings['employedTutorRegistrationDay'] ?? 'monday';
            $time = $settings['employedTutorRegistrationTime'] ?? '16:00';
        }
        
        $dayMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];
        
        $dayNumber = $dayMap[strtolower($day)] ?? 1;
        
        $registrationDate = Carbon::now()->startOfWeek()->addDays($dayNumber);
        
        $timeParts = explode(':', $time);
        $registrationDate->hour(intval($timeParts[0] ?? 0));
        $registrationDate->minute(intval($timeParts[1] ?? 0));
        $registrationDate->second(0);
        
        // Si on est après la date/heure d'inscription en fct du role, montrer la semaine suivante aussi
        return $now->greaterThanOrEqualTo($registrationDate);
    }

    public static function table(Table $table): Table
    {
        $userId = Auth::id();
        $showNextWeek = self::shouldShowNextWeek();
        
        $query = Creneaux::query()
            ->with([
                'tutor1.proposedUvs:code,code', 
                'tutor2.proposedUvs:code,code',
                'semaine'
            ])
            ->orderBy('start');
        
        $currentWeek = Semaine::where('date_debut', '<=', Carbon::now())
            ->where('date_fin', '>=', Carbon::now())
            ->first();
        
        if ($currentWeek) {
            $nextWeek = Semaine::where('numero', $currentWeek->numero + 1)
                ->where('fk_semestre', $currentWeek->fk_semestre)
                ->first();
            
            if ($showNextWeek && $nextWeek) {
                $query->whereIn('fk_semaine', [$currentWeek->id, $nextWeek->id]);
            } else {
                $query->where('fk_semaine', $currentWeek->id);
            }
        }
    
        return $table
            ->query($query)
            ->groups([
                Tables\Grouping\Group::make('day_and_time')
                    ->label(__('resources.common.fields.jour_et_horaire'))
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn(Creneaux $record) =>
                        ucfirst($record->start->translatedFormat('l d F Y')) . ' - ' . 
                        $record->start->format('H:i') . ' à ' . $record->end->format('H:i')
                    )
                    ->getKeyFromRecordUsing(fn(Creneaux $record) => 
                        $record->start->format('Y-m-d') . '_' . $record->start->format('H:i')
                    )
                    ->collapsible(true),
            ])
            ->defaultGroup('day_and_time')
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        TextColumn::make('fk_salle')
                            ->label(__('resources.common.fields.salle'))
                            ->icon('heroicon-o-map-pin')
                            ->color('gray'),
        
                        TextColumn::make('semaine.numero')
                            ->label(__('resources.common.fields.semaine'))
                            ->formatStateUsing(fn($state) => __('resources.common.format.semaine_numero', ['number' => $state]))
                            ->icon('heroicon-o-calendar')
                            ->color('gray'),
                    ]),    
                    Tables\Columns\Layout\Split::make([
                        TextColumn::make('tutor1.firstName')
                            ->label(__('resources.common.fields.tuteur1'))
                            ->icon('heroicon-o-user')
                            ->color('gray')
                            ->placeholder(__('resources.common.placeholders.none')),
        
                        TextColumn::make('tutor2.firstName')
                            ->label(__('resources.common.fields.tuteur2'))
                            ->icon('heroicon-o-user')
                            ->color('gray')
                            ->placeholder(__('resources.common.placeholders.none')),
                    ]),
    
                    TextColumn::make('id')
                        ->label(__('resources.common.fields.uvs_proposees'))
                        ->formatStateUsing(function ($state, Creneaux $creneau) {
                            $uvs = collect();
                    
                            if ($creneau->tutor1 && $creneau->tutor1->proposedUvs) {
                                $uvs = $uvs->merge($creneau->tutor1->proposedUvs->pluck('code'));
                            }
                    
                            if ($creneau->tutor2 && $creneau->tutor2->proposedUvs) {
                                $uvs = $uvs->merge($creneau->tutor2->proposedUvs->pluck('code'));
                            }
                    
                            return $uvs->unique()->sort()->implode(', ') ?: __('resources.common.placeholders.none');
                        })
                        ->icon('heroicon-o-academic-cap')
                        ->color('primary'),
                ])
            ])
            ->contentGrid([
                'sm' => 2,
                'md' => 3,
                'lg' => 4,
                'xl' => 4,
            ])
            ->actions([
                Action::make('toggleShotgun1')
                    ->label(fn(Creneaux $record) => $record->tutor1_id === $userId ? __('resources.common.buttons.se_desinscrire') : __('resources.common.buttons.shotgun_1'))
                    ->color(fn(Creneaux $record) => $record->tutor1_id === $userId ? 'danger' : 'primary')
                    ->button()
                    ->visible(fn(Creneaux $record) =>
                        ($record->tutor1_id === null && $record->tutor2_id !== $userId) || $record->tutor1_id === $userId
                    )
                    ->action(function (Creneaux $record) use ($userId) {
                        if ($record->tutor1_id === $userId) {
                            $record->update(['tutor1_id' => null]);
                        } elseif (!$record->tutor1_id) {
                            $record->update(['tutor1_id' => $userId]);
                        }
                    }),
            
                Action::make('toggleShotgun2')
                    ->label(fn(Creneaux $record) => $record->tutor2_id === $userId ? __('resources.common.buttons.se_desinscrire') : __('resources.common.buttons.shotgun_2'))
                    ->color(fn(Creneaux $record) => $record->tutor2_id === $userId ? 'danger' : 'primary')
                    ->button()
                    ->visible(fn(Creneaux $record) =>
                        ($record->tutor2_id === null && $record->tutor1_id !== $userId) || $record->tutor2_id === $userId
                    )
                    ->action(function (Creneaux $record) use ($userId) {
                        if ($record->tutor2_id === $userId) {
                            $record->update(['tutor2_id' => null]);
                        } elseif (!$record->tutor2_id) {
                            $record->update(['tutor2_id' => $userId]);
                        }
                    }),
            ]) 
            ->paginated(false)
            ->recordUrl(null);
    }          

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreneau::route('/'),
        ];
    }
}