<?php

namespace App\Filament\Resources\Tutor;

use App\Filament\Resources\Tutor\ComptabiliteResource\Pages;
use App\Models\Comptabilite;
use App\Models\Creneaux;
use App\Enums\Roles;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Resource;

class ComptabiliteResource extends Resource
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
            ->schema(function () {
                $user = Auth::user();
                $semestreActif = \App\Models\Semestre::where('is_active', true)->first();
    
                if (!$semestreActif) return [];
    
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
    
                    return $semaines->map(function ($semaine) use ($allCreneaux, $user) {
                        $creneaux = $allCreneaux[$semaine->id] ?? collect();
                    
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
                                    Forms\Components\Grid::make(3)
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
                                            Forms\Components\TextInput::make('nb_heures')
                                                ->label('Durée (heures)')
                                                ->numeric()
                                                ->minValue(0.25)
                                                ->step(0.25)
                                                ->required(),
                    
                                            Forms\Components\Textarea::make('commentaire')
                                                ->label('Commentaire')
                                                ->required()
                                                ->rows(2),
                                        ])
                                        ->default($heuresSupp) // <<< Ajouter ça ici
                                        ->columns(2)
                                        ->columnSpanFull(),
                                ])                        
                        ])->columnSpanFull();
                    })->toArray();                    
            });
    }      

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('semaine.numero')
                    ->label('Semaine'),

                Tables\Columns\TextColumn::make('nb_heures')
                    ->label('Heures comptabilisées'),

                Tables\Columns\TextColumn::make('commentaire_bve')
                    ->limit(50),
            ])
            ->filters([])
            ->paginated(false)
            ->recordUrl(null)
            ->defaultSort('semaine_id');
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
            'index' => Pages\ListComptabilites::route('/'),
            'create' => Pages\CreateComptabilite::route('/create'),
        ];
    }
}