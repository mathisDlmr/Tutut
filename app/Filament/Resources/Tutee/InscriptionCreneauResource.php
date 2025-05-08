<?php

namespace App\Filament\Resources\Tutee;

use App\Enums\Roles;
use App\Filament\Resources\Tutee\InscriptionCreneauResource\Pages;
use App\Models\Creneaux;
use App\Models\Inscription;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Illuminate\Support\Collection;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\Split;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class InscriptionCreneauResource extends Resource
{
    protected static ?string $model = Creneaux::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Créneaux disponibles';
    protected static ?string $pluralModelLabel = 'Créneaux disponibles';

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function formatGroupedUvs(Collection $codes): string
    {
        return $codes
            ->sort()
            ->groupBy(fn($code) => substr($code, 0, 2))
            ->map(function ($group, $prefix) {
                $suffixes = $group->map(fn($code) => substr($code, 2))->sort()->join('/');
                return $prefix . $suffixes;
            })
            ->values()
            ->join("\n");
    }    

    public static function table(Table $table): Table
    {
        $userId = Auth::id();

        return $table
            ->query(
                Creneaux::query()
                    ->with([
                        'tutor1.proposedUvs', 
                        'tutor2.proposedUvs',
                        'inscriptions'
                    ])   
                    ->withCount('inscriptions')
                    ->where('open', true)
                    ->where(function ($query) {
                        $query->whereNotNull('tutor1_id')
                              ->orWhereNotNull('tutor2_id');
                    })                    
                    ->orderBy('start')
            )
            ->groups([
                Tables\Grouping\Group::make('day_and_time')
                    ->label('Jour et horaire')
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
                Stack::make([
                    Split::make([
                        TextColumn::make('tutor1.firstName')
                            ->label('Tuteur 1')
                            ->icon('heroicon-o-user')
                            ->color('gray')
                            ->placeholder('—')
                            ->formatStateUsing(function ($state, $record) {
                                $languages = is_string($record->tutor1->languages) 
                                    ? json_decode($record->tutor1->languages, true) 
                                    : ($record->tutor1->languages ?? []);
                                $flags = collect($languages)->map(function ($lang) {
                                    return match ($lang) {
                                        'en' => '🇬🇧',
                                        'es' => '🇪🇸',
                                        'zh' => '🇨🇳',
                                        'de' => '🇩🇪',
                                        'ar' => '🇸🇦',
                                        'ru' => '🇷🇺',
                                        'ja' => '🇯🇵',
                                        'it' => '🇮🇹',
                                        default => null,
                                    };
                                })->filter()->implode(' ');
                                return $state . ($flags ? " {$flags}" : '');
                            }),

                        TextColumn::make('tutor2.firstName')
                            ->label('Tuteur 2')
                            ->icon('heroicon-o-user')
                            ->color('gray')
                            ->placeholder('—')
                            ->formatStateUsing(function ($state, $record) {
                                $languages = is_string($record->tutor2->languages) 
                                    ? json_decode($record->tutor2->languages, true) 
                                    : ($record->tutor2->languages ?? []);
                                $flags = collect($languages)->map(function ($lang) {
                                    return match ($lang) {
                                        'en' => '🇬🇧',
                                        'es' => '🇪🇸',
                                        'zh' => '🇨🇳',
                                        'de' => '🇩🇪',
                                        'ar' => '🇸🇦',
                                        'ru' => '🇷🇺',
                                        'ja' => '🇯🇵',
                                        'it' => '🇮🇹',
                                        default => null,
                                    };
                                })->filter()->implode(' ');
                                return $state . ($flags ? " {$flags}" : '');
                            }),
                    ]),

                    Split::make([
                        TextColumn::make('fk_salle')
                            ->label('Salle')
                            ->icon('heroicon-o-map-pin')
                            ->color('gray'),
                        TextColumn::make('places')
                            ->label('Places')
                            ->icon('heroicon-o-user-group')
                            ->color('gray')
                            ->getStateUsing(function (Creneaux $record) {
                                $max = ($record->tutor2_id && $record->tutor1_id) ? 15 : 6;
                                return "{$record->inscriptions_count} / $max";
                            }),
                    ]),

                    TextColumn::make('id')
                        ->label('UVs proposées')
                        ->formatStateUsing(function ($state, Creneaux $creneau) {
                            $uvs = collect();
                    
                            foreach ([$creneau->tutor1, $creneau->tutor2] as $tutor) {
                                if ($tutor) {
                                    $tutor->loadMissing('proposedUvs');
                                    $uvs = $uvs->merge($tutor->proposedUvs->pluck('code'));
                                }
                            }
                    
                            $grouped = self::formatGroupedUvs($uvs->unique());
                    
                            $lines = explode("\n", $grouped);
                            $chunks = array_chunk($lines, ceil(count($lines) / 4));
                    
                            return '<div style="display: flex; gap: 1rem;">' .
                                collect($chunks)->map(fn($col) =>
                                    '<div style="flex:1;">' . implode('<br>', $col) . '</div>'
                                )->implode('') .
                            '</div>';
                        })
                        ->icon('heroicon-o-academic-cap')
                        ->color('primary')
                        ->html(),                                              
                ])
            ])
            ->actions([
                Action::make('s_inscrire')
                    ->label("S'inscrire")
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->form(fn(Creneaux $record) => [
                        Forms\Components\Select::make('enseignements_souhaites')
                            ->label('UVs souhaitées')
                            ->multiple()
                            ->required()
                            ->options(
                                collect([$record->tutor1, $record->tutor2])
                                    ->filter()
                                    ->flatMap(fn($tutor) =>
                                        $tutor->proposedUvs->mapWithKeys(fn($uv) => [
                                            $uv->code => "{$uv->code} - {$uv->intitule}"
                                        ])
                                    )
                                    ->unique()
                            )
                            ->placeholder('Choisissez vos UVs')
                            ->maxItems(3),
                    ])                    
                    ->visible(function (Creneaux $record) use ($userId) {
                        $max = $record->tutor2_id ? 15 : 6;
                        return !$record->inscriptions->contains('tutee_id', $userId)
                            && $record->inscriptions_count < $max
                            && Auth::user()->role !== Roles::Administrator->value;
                    })
                    ->action(function (array $data, Creneaux $record) use ($userId) {
                        Inscription::create([
                            'tutee_id' => $userId,
                            'creneau_id' => $record->id,
                            'enseignements_souhaites' => json_encode($data['enseignements_souhaites']),
                        ]);
                    }),
                Action::make('se_desinscrire')
                    ->label('Se désinscrire')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->button()
                    ->visible(function (Creneaux $record) use ($userId) {
                        return $record->inscriptions->contains('tutee_id', $userId);
                    })
                    ->action(function (Creneaux $record) use ($userId) {
                        $record->inscriptions()->where('tutee_id', $userId)->delete();
                    }),
                Action::make('voir_inscrits')
                    ->label('Voir les inscrit·e·s')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Liste des inscrit·e·s')
                    ->modalButton('Fermer')
                    ->modalCancelAction(false)
                    ->visible(fn (Creneaux $record) => $record->inscriptions_count > 0)
                    ->modalContent(function (Creneaux $record) {
                        $html = '<ul class="space-y-2">';
                    
                        foreach ($record->inscriptions as $inscription) {
                            $user = $inscription->tutee;
                            $uvs = collect(json_decode($inscription->enseignements_souhaites ?? '[]'))
                                ->sort()
                                ->implode(', ');
                    
                            $html .= "<li>
                                        <strong>• {$user->firstName} {$user->lastName}</strong> : {$uvs}<br>
                                      </li>";
                        }
                    
                        $html .= '</ul>';
                    
                        return new HtmlString($html);
                    })                    
                    ->disabled(fn(Creneaux $record) => $record->inscriptions_count === 0)
                    ->button()
                    ->outlined()
            ])
            ->contentGrid([
                'sm' => 2,
                'md' => 3,
            ])
            ->paginated(false)
            ->recordUrl(null);
    }          

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInscriptionCreneaus::route('/'),
        ];
    }
}