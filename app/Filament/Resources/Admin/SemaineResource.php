<?php

namespace App\Filament\Resources\Admin;

use App\Enums\Roles;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\Semaine;
use App\Models\Semestre;
use App\Models\Creneaux;
use App\Models\DispoSalle;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use App\Filament\Resources\Admin\SemaineResource\Pages;
use Filament\Notifications\Notification;

class SemaineResource extends Resource
{
    protected static ?string $model = Semaine::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Gestion';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::Administrator->value ||
               Auth::user()->role === Roles::EmployedPrivilegedTutor->value);
    }       

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('numero')
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Select::make('fk_semestre')
                        ->relationship('semestre', 'code')
                        ->required()
                        ->columnSpan(1),

                    Toggle::make('is_vacances')
                        ->label("Vacances")
                        ->helperText("Aucune génération de créneaux ne sera faite cette semaine")
                        ->columnSpan(1),
                ]),

            Forms\Components\Grid::make(2)
                ->schema([
                    DatePicker::make('date_debut')
                        ->required()
                        ->columnSpan(1),

                    DatePicker::make('date_fin')
                        ->required()
                        ->columnSpan(1),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('Créer la prochaine semaine')
                    ->action(function () {
                        $semestre = Semestre::where('is_active', true)->first();
            
                        if (!$semestre) {
                            throw new \Exception("Aucun semestre actif.");
                        }
            
                        $dernierNumero = Semaine::where('fk_semestre', $semestre->code)->max('numero') ?? 0;
            
                        $lastWeek = Semaine::where('fk_semestre', $semestre->code)
                            ->orderByDesc('numero')
                            ->first();
            
                        if ($lastWeek && $lastWeek->date_fin) {
                            $date_debut = \Carbon\Carbon::parse($lastWeek->date_fin)->addDay();
                        } else {
                            $date_debut = \Carbon\Carbon::parse($semestre->debut);
                        }
            
                        $date_fin = $date_debut->copy()->addDays(6);
            
                        if ($date_fin->gt($semestre->fin)) {
                            throw new \Exception("Cette semaine dépasse la fin du semestre.");
                        }
            
                        Semaine::create([
                            'numero' => $dernierNumero + 1,
                            'fk_semestre' => $semestre->code,
                            'date_debut' => $date_debut,
                            'date_fin' => $date_fin,
                            'is_vacances' => false,
                        ]);
                    })
                    ->color('primary')
                    ->icon('heroicon-o-plus'),
            ])        
            ->columns([
                Tables\Columns\TextColumn::make('numero'),
                Tables\Columns\TextColumn::make('semestre.code')->label('Semestre'),
                Tables\Columns\TextColumn::make('date_debut')
                    ->label('Date de début')
                    ->formatStateUsing(fn (string $state) => Carbon::parse($state)->locale('fr')->translatedFormat('d F Y')),
                Tables\Columns\TextColumn::make('date_fin')
                    ->label('Date de fin')
                    ->formatStateUsing(fn (string $state) => Carbon::parse($state)->locale('fr')->translatedFormat('d F Y')),
                Tables\Columns\TextColumn::make('is_vacances')
                    ->label('Vacances')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Oui' : 'Non'),
            ])
            ->filters([
                Tables\Filters\Filter::make('future')
                    ->label('Semaines futures')
                    ->query(fn (Builder $query) => $query->where('date_fin', '>', now()))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Action::make('générerCréneaux')
                    ->label('(Re)Générer les créneaux')
                    ->action(fn (Semaine $record) => static::genererCreneaux($record))
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-o-plus-circle'),
                Action::make('ouvrirCreneaux')
                    ->label('Ouvrir créneaux')
                    ->icon('heroicon-o-lock-open')
                    ->requiresConfirmation()
                    ->action(function (Semaine $record) {
                        Creneaux::all()->update(['open' => false]);
                        Creneaux::where('fk_semaine', $record->id)->update(['open' => true]);
                        Notification::make()
                            ->title("Tous les créneaux de la semaine {$record->numero} sont maintenant ouverts.")
                            ->success()
                            ->send();
                    }),
                Action::make('vacances')
                    ->label('Vacances')
                    ->icon('heroicon-o-sun')
                    ->requiresConfirmation()
                    ->action(fn (Semaine $record) => $record->update(['is_vacances' => !$record->is_vacances])),
            ]);
    }

    public static function genererCreneaux(Semaine $semaine): void
    {
        if ($semaine->is_vacances) {
            Notification::make()
                ->title('Pas de génération de créneaux pour les semaines de vacances.')
                ->danger()
                ->send();
            return;
        }
    
        Creneaux::where('fk_semaine', $semaine->id)->delete();
    
        $horairesStandards = [
            'Lundi' => ['12:30', '18:40', '19:40'],
            'Mardi' => ['12:30', '18:40', '19:40'],
            'Mercredi' => ['12:30', '18:40', '19:40'],
            'Jeudi' => ['12:30', '18:40', '19:40'],
            'Vendredi' => ['12:30', '18:40', '19:40'],
            'Samedi' => ['10:30'],
        ];
    
        $horairesSpeciaux = [
            '08:00' => 120,
            '10:00' => 120,
            '12:30' => 90,
            '14:30' => 120,
            '16:30' => 120,
            '18:40' => 60,
            '19:40' => 60,
        ];
    
        $joursMap = [
            'Lundi' => 'Monday',
            'Mardi' => 'Tuesday',
            'Mercredi' => 'Wednesday',
            'Jeudi' => 'Thursday',
            'Vendredi' => 'Friday',
            'Samedi' => 'Saturday',
        ];
    
        $duréesStandards = [
            '12:30' => 90,
            '18:40' => 60,
            '19:40' => 60,
            '10:30' => 90,
        ];
    
        $semestre = Semestre::where('code', $semaine->fk_semestre)->first();
        $semestreStart = Carbon::parse($semestre->debut)->startOfWeek();
        $baseDate = $semestreStart->copy()->addWeeks($semaine->numero - 1);
    
        $jours = array_keys($joursMap);
    
        foreach ($jours as $jour) {
            $jourIndex = array_search($jour, array_keys($joursMap));
            $dateDuJour = $baseDate->copy()->addDays($jourIndex);
            $jourLabel = $jour;
            $horairesDuJour = $horairesStandards[$jour] ?? [];
            $durées = $duréesStandards;
    
            if ($semestre) {
                if (
                    $semestre->debut_medians && $semestre->fin_medians &&
                    $dateDuJour->between($semestre->debut_medians, $semestre->fin_medians)
                ) {
                    $jourLabel = 'Médians';
                    $horairesDuJour = array_keys($horairesSpeciaux);
                    $durées = $horairesSpeciaux;
                } elseif (
                    $semestre->debut_finaux && $semestre->fin_finaux &&
                    $dateDuJour->between($semestre->debut_finaux, $semestre->fin_finaux)
                ) {
                    $jourLabel = 'Finaux';
                    $horairesDuJour = array_keys($horairesSpeciaux);
                    $durées = $horairesSpeciaux;
                }
            }
    
            $dispos = DispoSalle::where('jour', $jourLabel)->get();
    
            foreach ($dispos as $dispo) {
                $salleNumero = $dispo->fk_salle;
    
                foreach ($horairesDuJour as $heure) {
                    $durée = $durées[$heure];
                    $startTime = Carbon::parse($heure);
                    $endTime = $startTime->copy()->addMinutes($durée);
    
                    if (
                        $startTime->format('H:i:s') >= $dispo->debut &&
                        $endTime->format('H:i:s') <= $dispo->fin
                    ) {
                        $start = $dateDuJour->copy()->setTimeFromTimeString($heure);
                        $end = $start->copy()->addMinutes($durée);
    
                        Creneaux::create([
                            'tutor1_id' => null,
                            'tutor2_id' => null,
                            'fk_semaine' => $semaine->id,
                            'fk_salle' => $salleNumero,
                            'start' => $start,
                            'end' => $end,
                        ]);
                    }
                }
            }
        }
    
        Notification::make()
            ->title("Créneaux générés avec succès pour la semaine {$semaine->numero}.")
            ->success()
            ->send();
    }      

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSemaines::route('/'),
            'edit' => Pages\EditSemaine::route('/{record}/edit'),
        ];
    }
}
