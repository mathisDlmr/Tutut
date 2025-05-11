<?php

namespace App\Filament\Resources\Tutor;

use App\Filament\Resources\Tutor\ComptabiliteTutorResource\Pages;
use App\Models\Comptabilite;
use App\Models\Creneaux;
use App\Enums\Roles;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Resource;

class ComptabiliteTutorResource extends Resource
{
    protected static ?string $model = Comptabilite::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $label = 'Comptabilité';
    protected static ?string $pluralModelLabel = 'Comptabilité';
    protected static ?string $navigationGroup = 'Tutorat';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::EmployedPrivilegedTutor->value
            || Auth::user()->role === Roles::EmployedTutor->value);
    }  

    public function toggleCreneauCompted($creneauId, $value)
    {
        $user = Auth::user();
        $creneau = Creneaux::findOrFail($creneauId);
    
        if ($creneau->tutor1_id === $user->id) {
            $creneau->tutor1_compted = $value;
        } elseif ($creneau->tutor2_id === $user->id) {
            $creneau->tutor2_compted = $value;
        } else {
            abort(403, 'Non autorisé');
        }
    
        $creneau->save();
    }  

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('filter_uncounted')
                    ->label('Afficher uniquement les créneaux non comptés')
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('refresh_key', now()))
                    ->columnSpanFull(),
    
                Forms\Components\Hidden::make('refresh_key'),
    
                Forms\Components\Group::make()->schema(function (Forms\Get $get) {
                    $user = Auth::user();
                    $semestreActif = \App\Models\Semestre::where('is_active', true)->first();
    
                    if (!$semestreActif) return [];
    
                    $filterUncounted = $get('filter_uncounted') ?? false;
    
                    $semaines = \App\Models\Semaine::where('fk_semestre', $semestreActif->code)
                        ->orderByDesc('numero')
                        ->get();
    
                    $allCreneaux = \App\Models\Creneaux::with(['salle', 'inscriptions', 'semaine'])
                        ->where(function ($q) use ($user) {
                            $q->where('tutor1_id', $user->id)
                                ->orWhere('tutor2_id', $user->id);
                        })
                        ->whereHas('inscriptions')
                        ->get()
                        ->groupBy('fk_semaine');
    
                    return $semaines->map(function ($semaine) use ($allCreneaux, $user, $filterUncounted) {
                        $creneaux = $allCreneaux[$semaine->id] ?? collect();
    
                        if ($filterUncounted) {
                            $creneaux = $creneaux->filter(function ($creneau) use ($user) {
                                $key = $creneau->tutor1_id === $user->id ? 'tutor1_compted' : 'tutor2_compted';
                                return $creneau->$key === null;
                            });
                        }
    
                        $heuresSupp = \App\Models\HeuresSupplementaires::where('user_id', $user->id)
                            ->where('semaine_id', $semaine->id)
                            ->get()
                            ->map(fn ($heure) => [
                                'nb_heures' => $heure->nb_heures,
                                'commentaire' => $heure->commentaire,
                            ])
                            ->toArray();
    
                        return Forms\Components\Group::make([
                            Forms\Components\Section::make("Semaine {$semaine->numero} — du {$semaine->date_debut->format('d/m')} au {$semaine->date_fin->format('d/m')}")
                                ->schema([
                                    $creneaux->isEmpty()
                                    ? Forms\Components\View::make('filament.components.empty-states.no-creneaux')
                                        ->columnSpanFull()
                                    : Forms\Components\Grid::make(3)
                                        ->schema(
                                            $creneaux->map(function ($creneau) use ($user) {
                                                $tutorKey = $creneau->tutor1_id === $user->id ? 'tutor1_compted' : 'tutor2_compted';
                                                $isCounted = $creneau->$tutorKey;
                                                return Forms\Components\View::make('filament.components.form.slot-creneau')
                                                    ->viewData([
                                                        'creneau' => $creneau,
                                                        'isCounted' => $isCounted,
                                                    ])
                                                    ->columnSpan(1);
                                            })->toArray()
                                        ),
    
                                    Forms\Components\Repeater::make("heures_supplementaires_{$semaine->id}")
                                        ->label('Heures supplémentaires')
                                        ->schema([
                                            Grid::make(2)
                                                ->schema([
                                                    TextInput::make('nb_heures')
                                                        ->label('Durée (heures)')
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->step(0.5)
                                                        ->default('')
                                                        ->required(),
                                                    TextInput::make('commentaire')
                                                        ->label("Justification")
                                                        ->placeholder('Justification des heures supplémentaires')
                                                        ->required(),
                                                ])
                                        ])
                                        ->default($heuresSupp ?? [])
                                        ->collapsible()
                                        ->collapsed()
                                        ->itemLabel(fn (array $state): ?string =>
                                            isset($state['nb_heures']) ? "{$state['nb_heures']} heure(s) - {$state['commentaire']}" : null
                                        )
                                        ->columnSpanFull()
                                        ->visible(fn () => !$filterUncounted)
                                ])
                        ])->columnSpanFull();
                    })->toArray();
                })->columnSpanFull()
            ]);
    }        

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('semaine.numero')
                    ->label('Semaine')
                    ->sortable(),
                Tables\Columns\TextColumn::make('nb_heures')
                    ->label('Heures comptabilisées'),
                Tables\Columns\TextColumn::make('commentaire_bve')
                    ->limit(50),
                Tables\Columns\IconColumn::make('saisie')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
            ])
            ->defaultSort('semaine.numero', 'asc')
            ->modifyQueryUsing(function ($query) {
                $user = Auth::user();
                return $query->where('fk_user', $user->id);
            })
            ->filters([])
            ->paginated(false)
            ->recordUrl(null);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTutorComptabilites::route('/'),
            'create' => Pages\CreateTutorComptabilite::route('/create'),
        ];
    }
}