<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\ComptabiliteResource\Pages;
use App\Models\Comptabilite;
use App\Models\Semaine;
use App\Models\Semestre;
use App\Models\User;
use App\Enums\Roles;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Illuminate\Support\Collection;

class ComptabiliteResource extends Resource
{
    protected static ?string $model = Comptabilite::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $label = 'Comptabilité';
    protected static ?string $pluralModelLabel = 'Comptabilité';
    protected static ?string $navigationGroup = 'Administration';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && Auth::user()->role === Roles::Administrator->value;
    }  

    public static function form(Form $form): Form
    {
        return $form->schema([
            Hidden::make('fk_user'),
            Hidden::make('fk_semaine'),
            Textarea::make('commentaire_bve')
                ->label('Commentaire BVE')
                ->placeholder('Ajouter un commentaire pour le BVE')
                ->required()
        ]);
    }

    public static function table(Table $table): Table
    {
        $semestreActif = Semestre::where('is_active', true)->first();
        
        if (!$semestreActif) {
            return $table
                ->query(User::query()->where('id', 0)) 
                ->columns([
                    TextColumn::make('id')->label('Pas de semestre actif')
                ]);
        }
        
        $semaines = Semaine::where('fk_semestre', $semestreActif->code)
            ->orderBy('numero')
            ->get();
        
        // Récupérer tous les tuteurs ayant des comptabilités
        $employedTutorIds = DB::table('comptabilite')
            ->whereIn('fk_semaine', $semaines->pluck('id'))
            ->pluck('fk_user')
            ->unique();

        $employedTutors = User::whereIn('id', $employedTutorIds)
            ->whereIn('role', [
                Roles::EmployedTutor->value, 
                Roles::EmployedPrivilegedTutor->value
            ])
            ->orderBy('lastName')
            ->orderBy('firstName');

        // Pour chaque semaine, créer un groupe de mois
        $months = $semaines->groupBy(function ($semaine) {
            return Carbon::parse($semaine->date_debut)->format('Y-m');
        });
        
        // Préparer les options pour le filtre par mois
        $monthOptions = [];
        foreach ($months as $yearMonth => $semainesInMonth) {
            $firstSemaine = $semainesInMonth->first();
            $monthDisplay = ucfirst(Carbon::parse($firstSemaine->date_debut)->format('F Y'));
            $monthOptions[$yearMonth] = $monthDisplay;
        }
        
        // Créer les groupes pour chaque mois
        $monthGroups = [];
        foreach ($months as $yearMonth => $semainesInMonth) {
            $firstSemaine = $semainesInMonth->first();
            $monthDisplay = ucfirst(Carbon::parse($firstSemaine->date_debut)->format('F Y'));
            
            $monthGroups[] = Tables\Grouping\Group::make($yearMonth)
                ->label($monthDisplay)
                ->collapsible(true);
        }
        
        return $table
            ->query($employedTutors)
            ->groups($monthGroups)
            ->defaultGroup($months->keys()->first())
            ->filters([
                Tables\Filters\Filter::make('non_valides')
                    ->label('Non validés')
                    ->query(function ($query) use ($semaines) {
                        return $query->whereHas('comptabilites', function ($q) use ($semaines) {
                            $q->whereIn('fk_semaine', $semaines->pluck('id'))
                              ->where('saisie', false);
                        });
                    })
                    ->default(),
                
                // Ajout d'un filtre par mois
                Tables\Filters\SelectFilter::make('month')
                    ->label('Mois')
                    ->options($monthOptions)
                    ->query(function ($query, array $data) use ($semaines, $months) {
                        if (!$data['value']) {
                            return $query;
                        }
                        
                        $selectedYearMonth = $data['value'];
                        $semainesInMonth = $months[$selectedYearMonth] ?? collect();
                        
                        return $query->whereHas('comptabilites', function ($q) use ($semainesInMonth) {
                            $q->whereIn('fk_semaine', $semainesInMonth->pluck('id'))
                              ->where('nb_heures', '>', 0);
                        });
                    })
            ])         
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        TextColumn::make('firstName')
                            ->label('Tuteur')
                            ->formatStateUsing(fn($state, User $record) => 
                                $record->firstName . ' ' . $record->lastName
                            )
                            ->weight('bold')
                            ->size('medium')
                            ->extraAttributes([
                                'class' => 'grow whitespace-nowrap',
                            ])
                            ->searchable(['firstName', 'lastName']),
                        IconColumn::make('valide')
                            ->label('Validé')
                            ->boolean()
                            ->size('xl')
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->getStateUsing(function (User $user) use ($semaines) {
                                $comptabilites = Comptabilite::where('fk_user', $user->id)
                                    ->whereIn('fk_semaine', $semaines->pluck('id'))
                                    ->get();
                                
                                if ($comptabilites->isEmpty()) {
                                    return false;
                                }
                                
                                return $comptabilites->every(fn($compta) => $compta->saisie);
                            }),
                    ]),
                    ViewColumn::make('semaines_heures')
                        ->label('Semaines et heures')
                        ->view('filament.tables.columns.semaines-heures')
                        ->extraAttributes(['class' => 'flex items-center gap-2'])
                        ->getStateUsing(function (User $user, string $group = null) use ($semaines) {
                            // Détermine les semaines pertinentes pour l'affichage
                            $relevantSemaines = $semaines;
                            if ($group) {
                                $relevantSemaines = $semaines->filter(function ($semaine) use ($group) {
                                    return Carbon::parse($semaine->date_debut)->format('Y-m') === $group;
                                });
                            }
                            
                            // Récupérer les comptabilités de l'utilisateur pour ces semaines
                            $comptabilites = Comptabilite::where('fk_user', $user->id)
                                ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                                ->where('nb_heures', '>', 0)  // Filtre les semaines avec des heures
                                ->get()
                                ->keyBy('fk_semaine');
                            
                            // Préparer les données pour la vue
                            $result = [];
                            foreach ($relevantSemaines as $semaine) {
                                $compta = $comptabilites->get($semaine->id);
                                if ($compta && $compta->nb_heures > 0) {
                                    $result[] = [
                                        'semaine' => $semaine,
                                        'heures' => $compta->nb_heures,
                                        'validated' => $compta->saisie,
                                        'commentaire_bve' => $compta->commentaire_bve
                                    ];
                                }
                            }
                            
                            return $result;
                        }),
                    ViewColumn::make('total_heures')
                        ->label('Total')
                        ->view('filament.tables.columns.total-heures')
                        ->extraAttributes(['class' => 'flex items-center gap-2 font-bold text-primary-600'])
                        ->getStateUsing(function (User $user, string $group = null) use ($semaines) {
                            // Détermine les semaines pertinentes pour l'affichage
                            $relevantSemaines = $semaines;
                            if ($group) {
                                $relevantSemaines = $semaines->filter(function ($semaine) use ($group) {
                                    return Carbon::parse($semaine->date_debut)->format('Y-m') === $group;
                                });
                            }
                            
                            // Calculer le total des heures pour ces semaines
                            return Comptabilite::where('fk_user', $user->id)
                                ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                                ->sum('nb_heures');
                        }),
                ])
            ])
            ->contentGrid([
                'sm' => 1,
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->actions([
                Action::make('commentaire_bve')
                    ->label('Commenter')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->button()
                    ->form(function (User $record, string $group = null) use ($semaines) {
                        $form = [];
                        
                        // Filtrer les semaines pour ce mois si un groupe est spécifié
                        $relevantSemaines = $semaines;
                        if ($group) {
                            $relevantSemaines = $semaines->filter(function ($semaine) use ($group) {
                                return Carbon::parse($semaine->date_debut)->format('Y-m') === $group;
                            });
                        }
                        
                        // Récupérer les comptabilités existantes pour ces semaines
                        $comptabilites = Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                            ->where('nb_heures', '>', 0)
                            ->get()
                            ->keyBy('fk_semaine');
                        
                        foreach ($relevantSemaines as $semaine) {
                            $comptabilite = $comptabilites->get($semaine->id);
                            $totalHeures = $comptabilite ? $comptabilite->nb_heures : 0;
                            
                            if ($totalHeures > 0) {
                                $form[] = TextInput::make("commentaire_bve_{$semaine->id}")
                                    ->label("Commentaire Semaine {$semaine->numero} ({$totalHeures} h)")
                                    ->default($comptabilite->commentaire_bve ?? '')
                                    ->placeholder('Ajouter un commentaire pour cette semaine');
                            }
                        }
                        
                        return $form;
                    })
                    ->action(function (array $data, User $record) use ($semaines) {
                        foreach ($semaines as $semaine) {
                            if (isset($data["commentaire_bve_{$semaine->id}"])) {
                                $comptabilite = Comptabilite::firstOrNew([
                                    'fk_user' => $record->id,
                                    'fk_semaine' => $semaine->id,
                                ]);
                                
                                if (!isset($comptabilite->nb_heures)) {
                                    $comptabilite->nb_heures = 0;
                                }
                                
                                $comptabilite->commentaire_bve = $data["commentaire_bve_{$semaine->id}"];
                                $comptabilite->save();
                            }
                        }
                    }),
                
                Action::make('valider')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Valider les heures')
                    ->modalDescription(fn (User $record) => "Voulez-vous valider les heures de {$record->firstName} {$record->lastName} ?")
                    ->modalSubmitActionLabel('Oui, valider')
                    ->action(function (User $record, string $group = null) use ($semaines) {
                        // Filtrer les semaines pour ce mois si un groupe est spécifié
                        $relevantSemaines = $semaines;
                        if ($group) {
                            $relevantSemaines = $semaines->filter(function ($semaine) use ($group) {
                                return Carbon::parse($semaine->date_debut)->format('Y-m') === $group;
                            });
                        }
                        
                        // Récupérer les comptabilités existantes pour ces semaines
                        $comptabilites = Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                            ->where('nb_heures', '>', 0)
                            ->get();
                        
                        foreach ($comptabilites as $comptabilite) {
                            $comptabilite->saisie = true;
                            $comptabilite->save();
                        }
                    })
                    ->visible(function (User $record, string $group = null) use ($semaines) {
                        // Filtrer les semaines pour ce mois si un groupe est spécifié
                        $relevantSemaines = $semaines;
                        if ($group) {
                            $relevantSemaines = $semaines->filter(function ($semaine) use ($group) {
                                return Carbon::parse($semaine->date_debut)->format('Y-m') === $group;
                            });
                        }
                        
                        return Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                            ->where('nb_heures', '>', 0)
                            ->where('saisie', false)
                            ->exists();
                    }),                

                Action::make('annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Annuler la validation des heures')
                    ->modalDescription(fn (User $record) => "Voulez-vous annuler la validation des heures de {$record->firstName} {$record->lastName} ?")
                    ->modalSubmitActionLabel('Oui, annuler')
                    ->action(function (User $record, string $group = null) use ($semaines) {
                        // Filtrer les semaines pour ce mois si un groupe est spécifié
                        $relevantSemaines = $semaines;
                        if ($group) {
                            $relevantSemaines = $semaines->filter(function ($semaine) use ($group) {
                                return Carbon::parse($semaine->date_debut)->format('Y-m') === $group;
                            });
                        }
                        
                        // Récupérer les comptabilités existantes pour ces semaines
                        $comptabilites = Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                            ->where('nb_heures', '>', 0)
                            ->get();
                        
                        foreach ($comptabilites as $comptabilite) {
                            $comptabilite->saisie = false;
                            $comptabilite->save();
                        }
                    })
                    ->visible(function (User $record, string $group = null) use ($semaines) {
                        // Filtrer les semaines pour ce mois si un groupe est spécifié
                        $relevantSemaines = $semaines;
                        if ($group) {
                            $relevantSemaines = $semaines->filter(function ($semaine) use ($group) {
                                return Carbon::parse($semaine->date_debut)->format('Y-m') === $group;
                            });
                        }
                        
                        $comptabilites = Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $relevantSemaines->pluck('id'))
                            ->where('nb_heures', '>', 0)
                            ->get();
                            
                        return $comptabilites->isNotEmpty() && 
                               $comptabilites->every(fn($compta) => $compta->saisie === true);
                    }), 
            ])
            ->paginated(false)
            ->recordUrl(null);
    }

    /**
     * Détermine les mois auxquels l'utilisateur appartient en fonction de ses comptabilités
     */
    protected static function getUserMonths(User $user, Collection $semaines)
    {
        $comptabilites = Comptabilite::where('fk_user', $user->id)
            ->whereIn('fk_semaine', $semaines->pluck('id'))
            ->where('nb_heures', '>', 0)
            ->get();
        
        if ($comptabilites->isEmpty()) {
            return [];
        }
        
        // Pour chaque comptabilité, trouver le mois correspondant
        $months = [];
        foreach ($comptabilites as $comptabilite) {
            $semaine = $semaines->firstWhere('id', $comptabilite->fk_semaine);
            if ($semaine) {
                $month = Carbon::parse($semaine->date_debut)->format('Y-m');
                $months[] = $month;
            }
        }
        
        // Retourner tous les mois uniques
        return array_unique($months);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComptabilite::route('/'),
        ];
    }
}