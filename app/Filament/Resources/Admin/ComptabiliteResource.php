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
        
        return $table
            ->query($employedTutors)
            ->groups([
                Tables\Grouping\Group::make('month')
                    ->label('Mois')
                    ->getTitleFromRecordUsing(function (User $record) use ($semaines) {
                        $premiereCompta = Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $semaines->pluck('id'))
                            ->join('semaines', 'comptabilite.fk_semaine', '=', 'semaines.id')
                            ->orderBy('semaines.date_debut')
                            ->select('semaines.*')
                            ->first();
                        
                        if ($premiereCompta) {
                            return ucfirst(Carbon::parse($premiereCompta->date_debut)->format('F Y'));
                        }
                        
                        $premiereSemaine = $semaines->first();
                        return ucfirst(Carbon::parse($premiereSemaine->date_debut)->format('F Y'));
                    })
                    ->collapsible(true)
            ])
            ->defaultGroup('month')
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
                        ->extraAttributes(['class' => 'flex items-center gap-2']),
                    ViewColumn::make('total_heures')
                        ->label('Total')
                        ->view('filament.tables.columns.total-heures')
                        ->extraAttributes(['class' => 'flex items-center gap-2 font-bold text-primary-600'])
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
                    ->form(function (User $record) use ($semaines) {
                        $form = [];
                        
                        foreach ($semaines as $semaine) {
                            $comptabilite = Comptabilite::firstOrNew([
                                'fk_user' => $record->id,
                                'fk_semaine' => $semaine->id,
                            ]);
                            
                            $totalHeures = $comptabilite->nb_heures ?? 0;                            
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
                    ->action(function (User $record) use ($semaines) {
                        foreach ($semaines as $semaine) {                           
                            $comptabilite = Comptabilite::where('fk_user', $record->id)
                                ->where('fk_semaine', $semaine->id)
                                ->first();
                            
                            $totalHeures = ($comptabilite ? $comptabilite->nb_heures : 0);
                            
                            if ($totalHeures > 0) {
                                if (!$comptabilite) {
                                    $comptabilite = new Comptabilite([
                                        'fk_user' => $record->id,
                                        'fk_semaine' => $semaine->id,
                                        'nb_heures' => 0,
                                    ]);
                                }
                                
                                $comptabilite->saisie = true;
                                $comptabilite->save();
                            }
                        }
                    })
                    ->visible(fn (User $record) => 
                        Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $semaines->pluck('id'))
                            ->where('saisie', false)
                            ->exists()
                    ),                

                Action::make('annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Annuler la validation des heures')
                    ->modalDescription(fn (User $record) => "Voulez-vous annuler la validation des heures de {$record->firstName} {$record->lastName} ?")
                    ->modalSubmitActionLabel('Oui, annuler')
                    ->action(function (User $record) use ($semaines) {
                        foreach ($semaines as $semaine) {
                            $comptabilite = Comptabilite::where('fk_user', $record->id)
                                ->where('fk_semaine', $semaine->id)
                                ->first();
                            
                            if ($comptabilite) {
                                $comptabilite->saisie = false;
                                $comptabilite->save();
                            }
                        }
                    })
                    ->visible(fn (User $record) => 
                        Comptabilite::where('fk_user', $record->id)
                            ->whereIn('fk_semaine', $semaines->pluck('id'))
                            ->where('saisie', true)
                            ->exists()
                    ), 
            ])
            ->paginated(false)
            ->recordUrl(null);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComptabilite::route('/'),
        ];
    }
}