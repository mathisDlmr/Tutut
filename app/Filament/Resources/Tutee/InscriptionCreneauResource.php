<?php

namespace App\Filament\Resources\Tutee;

use App\Enums\Roles;
use App\Filament\Resources\Tutee\InscriptionCreneauResource\Pages;
use App\Models\Creneaux;
use App\Models\Inscription;
use App\Models\Semaine;
use App\Models\Semestre;
use Carbon\Carbon;
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
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resource d'inscription aux créneaux pour les tutorés
 * 
 * Cette ressource permet aux tutorés de consulter et de s'inscrire
 * aux créneaux de tutorat disponibles.
 * Fonctionnalités :
 * - Affichage des créneaux par jour et horaire
 * - Informations détaillées sur chaque créneau (tuteurs, langues, UVs)
 * - Inscription et annulation d'inscription avec règles de délai
 * - Indication du nombre de places occupées/disponibles
 * - Export Excel pour les administrateurs
 * - Support multilingue avec affichage des drapeaux pour les langues maîtrisées par les tuteurs
 */
class InscriptionCreneauResource extends Resource
{
    protected static ?string $model = Creneaux::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 1;

    /**
     * Obtient le label de navigation pour la ressource
     * 
     * @return string Le label traduit pour la navigation
     */
    public static function getNavigationLabel(): string
    {
        return __('resources.inscription_creneau.navigation_label');
    }

    /**
     * Obtient le label du modèle pour la ressource
     * 
     * @return string Le label traduit pour le modèle
     */
    public static function getModelLabel(): string
    {
        return __('resources.inscription_creneau.navigation_label');
    }

    /**
     * Obtient le label pluriel du modèle pour la ressource
     * 
     * @return string Le label pluriel traduit pour le modèle
     */
    public static function getPluralModelLabel(): string
    {
        return __('resources.inscription_creneau.navigation_label');
    }

    /**
     * Configure le formulaire (non utilisé pour cette ressource)
     * 
     * @param Form $form Le formulaire à configurer
     * @return Form Le formulaire configuré
     */
    public static function form(Form $form): Form
    {
        return $form;
    }

    /**
     * Formate les codes d'UVs pour un affichage plus compact
     * 
     * Regroupe les codes d'UVs par préfixe pour optimiser l'affichage.
     * Par exemple, "MT41, MT42, MT45" devient "MT41/42/45"
     * 
     * @param Collection $codes Collection des codes d'UVs à formater
     * @return string Les codes formatés et regroupés
     */
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
    
    /**
     * Balance les éléments horizontalement dans une ligne
     * 
     * Cette méthode permet de réaliser un affichage de texte avec des éléments
     * qui doivent être affichés horizontalement, mais qui ne peuvent pas être
     * affichés tous en une seule ligne.
     * 
     * @param array $items Tableau d'éléments à afficher
     * @param int $maxCharsPerLine Nombre de caractères maximum par ligne
     * @return array Tableau d'éléments répartis sur plusieurs lignes
     */
    public static function balanceHorizontally(array $items, int $maxCharsPerLine): array
    {
        $lines = [];
        $currentLine = [];
        $currentLength = 0;

        foreach ($items as $item) {
            $itemLength = strlen($item);

            if ($currentLength + $itemLength + count($currentLine) * 2 > $maxCharsPerLine) {
                // Si dépasse, on ferme la ligne et commence une nouvelle
                $lines[] = $currentLine;
                $currentLine = [];
                $currentLength = 0;
            }

            $currentLine[] = $item;
            $currentLength += $itemLength;
        }

        if (!empty($currentLine)) {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    /**
     * Récupère les paramètres généraux depuis le fichier de configuration
     * 
     * @return array Tableau associatif des paramètres
     */
    public static function getSettings(): array
    {
        $settingsPath = 'settings.json';
        if (Storage::exists($settingsPath)) {
            return json_decode(Storage::get($settingsPath), true) ?? [];
        }
        return [];
    }

    /**
     * Détermine si la semaine suivante doit être affichée pour l'inscription
     * 
     * Cette méthode vérifie, en fonction des paramètres de configuration,
     * si la date/heure actuelle permet aux tutorés de voir les créneaux
     * de la semaine suivante.
     * 
     * @return bool Vrai si la semaine suivante doit être affichée
     */
    protected static function shouldShowNextWeek(): bool
    {
        $settings = self::getSettings();
        
        $registrationDay = $settings['tuteeRegistrationDay'] ?? 'sunday';
        $registrationTime = $settings['tuteeRegistrationTime'] ?? '16:00';
        
        $now = Carbon::now();
        $currentDayOfWeek = strtolower($now->englishDayOfWeek);
        
        if ($currentDayOfWeek === strtolower($registrationDay)) {  // Si on est le jour de changement, on vérifie l'heure
            list($hour, $minute) = explode(':', $registrationTime);  
            $registrationDateTime = Carbon::now()->setTime((int)$hour, (int)$minute, 0);
            return $now->greaterThanOrEqualTo($registrationDateTime);
        } else {   // On détermine si on est après le jour d'inscription
            $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            $registrationDayIndex = array_search(strtolower($registrationDay), $daysOfWeek);
            $currentDayIndex = array_search($currentDayOfWeek, $daysOfWeek);
            
            return ($currentDayIndex > $registrationDayIndex);
        }
    }

    /**
     * Vérifie si l'utilisateur peut annuler son inscription à un créneau
     * 
     * Applique diverses règles pour déterminer si l'annulation est possible :
     * - Interdiction d'annuler un créneau déjà commencé
     * - Option pour interdire l'annulation le jour même du créneau
     * - Règle de délai minimum avant le début du créneau
     * 
     * @param Creneaux $creneau Le créneau dont on veut vérifier la possibilité d'annulation
     * @return bool Vrai si l'annulation est possible
     */
    protected static function canChange(Creneaux $creneau): bool
    {
        $settings = self::getSettings();

        $now = Carbon::now();
        if ($now->greaterThan($creneau->start)) {
            return false;
        }
        
        // Si on utilise la règle "pas d'annulation le jour même"
        if (($settings['useOneDayBeforeCancellation'] ?? false) && 
            $now->format('Y-m-d') === $creneau->start->format('Y-m-d')) {
                return false;
        }
        
        // Si on a une durée minimale avant le créneau
        if (!empty($settings['minTimeCancellationTime'])) {
            list($hours, $minutes) = explode(':', $settings['minTimeCancellationTime']);
            $minTimeInMinutes = ((int)$hours * 60) + (int)$minutes;
            
            $diffInMinutes = $now->diffInMinutes($creneau->start, false);
            return $diffInMinutes >= $minTimeInMinutes;
        }
        
        return true;
    }

    /**
     * Configure la table d'affichage des créneaux pour les tutorés
     * 
     * Cette méthode configure une interface avancée de visualisation
     * avec de nombreuses fonctionnalités :
     * - Groupement des créneaux par jour et heure
     * - Affichage détaillé des informations (tuteurs, langue, salle, etc.)
     * - Actions d'inscription ou désinscription avec contrôle d'accès
     * - Export Excel (pour les administrateurs uniquement)
     * - Optimisation visuelle pour présenter de nombreuses informations
     * 
     * @param Table $table La table à configurer
     * @return Table La table configurée
     */
    public static function table(Table $table): Table
    {
        $userId = Auth::id();
        
        $activeSemester = Semestre::getActive();
        if (!$activeSemester) {
            return $table->query(Creneaux::query()->where('id', -1));
        }
        
        return $table
            ->headerActions([
                Action::make('export_excel')
                    ->label(__('resources.common.buttons.export_excel'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->button()
                    ->visible(fn() => Auth::user()->role === Roles::Administrator->value)
                    ->action(function () {
                        return self::exportExcel();
                    })
            ])
            ->query(
                Creneaux::query()
                    ->with([
                        'tutor1.proposedUvs', 
                        'tutor2.proposedUvs',
                        'inscriptions'
                    ])   
                    ->withCount('inscriptions')
                    ->where(function ($query) {
                        $query->whereNotNull('tutor1_id')
                              ->orWhereNotNull('tutor2_id');
                    })
                    ->orderBy('start')
            )
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
                Stack::make([
                    Split::make([
                        TextColumn::make('tutor1.firstName')
                            ->label(__('resources.common.fields.tuteur1'))
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
                                return $state . ' ' .($record->tutor1->lastName)[0].'.' . ($flags ? " {$flags}" : '');
                            }),

                        TextColumn::make('tutor2.firstName')
                            ->label(__('resources.common.fields.tuteur2'))
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
                                return $state . ' ' .($record->tutor2->lastName)[0].'.' . ($flags ? " {$flags}" : '');  
                            }),
                    ]),

                    Split::make([
                        TextColumn::make('fk_salle')
                            ->label(__('resources.common.fields.salle'))
                            ->icon('heroicon-o-map-pin')
                            ->color('gray'),
                        TextColumn::make('places')
                            ->label(__('resources.common.fields.places'))
                            ->icon('heroicon-o-user-group')
                            ->color('gray')
                            ->getStateUsing(function (Creneaux $record) {
                                $settings = self::getSettings();
                                $max = ($record->tutor1_id && $record->tutor2_id)
                                    ? (isset($settings['maxStudentFor2Tutors']) ? intval($settings['maxStudentFor2Tutors']) : 15)
                                    : (isset($settings['maxStudentFor1Tutor']) ? intval($settings['maxStudentFor1Tutor']) : 6);
                                return "{$record->inscriptions_count} / $max";
                            }),
                    ]),

                    TextColumn::make('id')
                        ->label(__('resources.common.fields.uvs_proposees'))
                        ->formatStateUsing(function ($state, Creneaux $creneau) {
                            $uvs = collect();

                            foreach ([$creneau->tutor1, $creneau->tutor2] as $tutor) {
                                if ($tutor) {
                                    $tutor->loadMissing('proposedUvs');
                                    $uvs = $uvs->merge($tutor->proposedUvs->pluck('code'));
                                }
                            }

                            $grouped = self::formatGroupedUvs($uvs->unique());
                            $items = explode("\n", $grouped);

                            $lines = self::balanceHorizontally($items, 35); // 35 caractères max/ligne

                            return collect($lines)->map(function ($lineItems) {
                                return implode('&nbsp;&nbsp;', $lineItems);
                            })->implode('<br>');
                        })
                        ->icon('heroicon-o-academic-cap')
                        ->color('primary')
                        ->html(),                                             
                ])
            ])
            ->actions([
                Action::make('s_inscrire')
                    ->label(__('resources.common.buttons.s_inscrire'))
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->form(fn(Creneaux $record) => [
                        Forms\Components\Select::make('enseignements_souhaites')
                            ->label(__('resources.common.fields.uvs_souhaitees'))
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
                        $settings = self::getSettings();
                        $max = ($record->tutor1_id && $record->tutor2_id)
                            ? (isset($settings['maxStudentFor2Tutors']) ? intval($settings['maxStudentFor2Tutors']) : 15)
                            : (isset($settings['maxStudentFor1Tutor']) ? intval($settings['maxStudentFor1Tutor']) : 6);
                        $alreadySubscribed = Inscription::where('tutee_id', $userId)
                            ->whereHas('creneau', function ($query) use ($record) {
                                $query->where('start', $record->start);
                            })->exists();
                        return !$record->inscriptions->contains('tutee_id', $userId)
                            && $record->inscriptions_count < $max
                            && Auth::user()->role !== Roles::Administrator->value
                            && Auth::id() !== $record->tutor1_id
                            && Auth::id() !== $record->tutor2_id
                            && $record->end > Carbon::now()
                            && !$alreadySubscribed
                            && self::canChange($record);
                    })
                    ->action(function (array $data, Creneaux $record) use ($userId) {
                        Inscription::create([
                            'tutee_id' => $userId,
                            'creneau_id' => $record->id,
                            'enseignements_souhaites' => json_encode($data['enseignements_souhaites']),
                        ]);
                    }),
                Action::make('se_desinscrire')
                    ->label(__('resources.common.buttons.se_desinscrire'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->button()
                    ->visible(function (Creneaux $record) use ($userId) {
                        return $record->inscriptions->contains('tutee_id', $userId) && 
                               self::canChange($record);
                    })
                    ->action(function (Creneaux $record) use ($userId) {
                        $record->inscriptions()->where('tutee_id', $userId)->delete();
                    }),
                Action::make('viewRegistrations')
                    ->label(__('resources.common.buttons.view_registrations'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(__('resources.inscription_creneau.modal_heading'))
                    ->modalButton(__('resources.common.buttons.close'))
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

    /**
     * Définit les pages disponibles pour cette ressource
     * 
     * Cette ressource ne contient qu'une page d'index qui liste
     * les créneaux disponibles pour l'inscription.
     * 
     * @return array Tableau associatif des pages
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInscriptionCreneaux::route('/'),
        ];
    }
    
    /**
     * Génère un export Excel des créneaux et inscriptions
     * 
     * Cette méthode crée un fichier Excel structuré par semaine, avec :
     * - Un onglet distinct pour chaque semaine du semestre actif
     * - Regroupement des créneaux par jour et horaire
     * - Affichage détaillé des informations pour chaque créneau :
     *   - Tuteurs assignés et leurs UVs
     *   - Salle et horaire
     *   - Liste des tutorés inscrits avec leurs UVs demandées
     * - Formatage avancé pour une meilleure lisibilité (couleurs, styles, etc.)
     * 
     * Accessible uniquement aux administrateurs depuis le bouton d'export
     * 
     * @return StreamedResponse Réponse HTTP contenant le fichier Excel en téléchargement
     */
    public static function exportExcel()
    {
        $activeSemester = Semestre::getActive();
        if (!$activeSemester) {
            return response()->json(['error' => 'Aucun semestre actif trouvé'], 404);
        }
        
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Créneaux du Semestre')
            ->setDescription('Export des créneaux du semestre actif');

        $semaines = Semaine::where('fk_semestre', $activeSemester->code)
            ->orderBy('date_debut')
            ->get();

        $spreadsheet->removeSheetByIndex(0);
        
        foreach ($semaines as $semaine) {
            $weekNumber = $semaine->numero_semaine ?? ($semaine->id - $semaines->first()->id + 1);
            $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, "Semaine $weekNumber");
            $spreadsheet->addSheet($sheet);
            $spreadsheet->setActiveSheetIndexByName("Semaine $weekNumber");
            
            // Premier Creneau
            $sheet->getColumnDimension('A')->setWidth(25);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(20);
            $sheet->getColumnDimension('D')->setWidth(30);

            $sheet->getColumnDimension('E')->setWidth(5); // Separateur

            // Second Creneau
            $sheet->getColumnDimension('F')->setWidth(25);
            $sheet->getColumnDimension('G')->setWidth(25);
            $sheet->getColumnDimension('H')->setWidth(20);
            $sheet->getColumnDimension('I')->setWidth(30);

            $sheet->getColumnDimension('J')->setWidth(5); // Separateur

            // Troisieme creneau
            $sheet->getColumnDimension('K')->setWidth(25);
            $sheet->getColumnDimension('L')->setWidth(25);
            $sheet->getColumnDimension('M')->setWidth(20);
            $sheet->getColumnDimension('N')->setWidth(30);
            
            $creneaux = Creneaux::with([
                    'tutor1.proposedUvs', 
                    'tutor2.proposedUvs',
                    'inscriptions.tutee',
                    'semaine'
                ])
                ->where('fk_semaine', $semaine->id)
                ->whereHas('inscriptions')
                ->orderBy('start')
                ->get();
        
            $creneauxByDay = $creneaux->groupBy(function ($creneau) {
                return $creneau->start->format('Y-m-d');
            });
            
            $rowIndex = 1;

            // Titre d'onglet'
            $sheet->setCellValue('A' . $rowIndex, "Créneaux de la Semaine $weekNumber");
            $sheet->mergeCells('A' . $rowIndex . ':N' . $rowIndex);
            $sheet->getStyle('A' . $rowIndex)->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $rowIndex += 2;
            
            foreach ($creneauxByDay as $day => $dayCreneaux) {
                // Header de feuille
                $dayHeader = ucfirst(Carbon::parse($day)->translatedFormat('l d F Y'));
                $sheet->setCellValue('A' . $rowIndex, $dayHeader);
                $sheet->mergeCells('A' . $rowIndex . ':N' . $rowIndex);
                $sheet->getStyle('A' . $rowIndex)->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A' . $rowIndex)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');
                $rowIndex++;
                
                $creneauxByTime = $dayCreneaux->groupBy(function ($creneau) {
                    return $creneau->start->format('H:i');
                });
                foreach ($creneauxByTime as $time => $timeCreneaux) {
                    // header heure
                    $firstCreneau = $timeCreneaux->first();
                    $timeHeader = $firstCreneau->start->format('H:i') . ' à ' . $firstCreneau->end->format('H:i');
                    $sheet->setCellValue('A' . $rowIndex, $timeHeader);
                    $sheet->mergeCells('A' . $rowIndex . ':N' . $rowIndex);
                    $sheet->getStyle('A' . $rowIndex)->getFont()->setItalic(true);
                    $sheet->getStyle('A' . $rowIndex)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                    $rowIndex++;
                    
                    $chunkedCreneaux = array_chunk($timeCreneaux->all(), 3);
                    foreach ($chunkedCreneaux as $creneauxGroup) {
                        $startRow = $rowIndex;
                        
                        $maxHeaderRows = 0;
                        $maxTuteeRows = 0;
                        $tuteeStartRows = [];
                        
                        // On met les infos pour chaque creneau (s'il y a des infos à mettre)
                        foreach ($creneauxGroup as $index => $creneau) {
                            $headerRows = 3;
                            if ($creneau->tutor1 && $creneau->tutor1->proposedUvs->count() > 0) {
                                $headerRows++;
                            }
                            
                            if ($creneau->tutor2) {
                                $headerRows++;
                                if ($creneau->tutor2->proposedUvs->count() > 0) {
                                    $headerRows++;
                                }
                            }
                            
                            $maxHeaderRows = max($maxHeaderRows, $headerRows);

                            $tuteeRows = max(1, count($creneau->inscriptions));
                            $maxTuteeRows = max($maxTuteeRows, $tuteeRows);
                            
                            $tuteeStartRows[$index] = $headerRows;
                        }
                        
                        foreach ($creneauxGroup as $index => $creneau) {
                            $colOffset = $index * 5;
                            $localRowIndex = $startRow;
                            
                            // Header salle
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'Salle: ' . $creneau->fk_salle);
                            $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                            $sheet->getStyle(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex)->getFont()->setBold(true);
                            $localRowIndex++;
                            
                            // Tutor 1
                            $tutor1Name = $creneau->tutor1 ? ($creneau->tutor1->firstName . ' ' . $creneau->tutor1->lastName) : '-';
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'Tuteur 1: ' . $tutor1Name);
                            $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                            $sheet->getStyle(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex)
                                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2EFDA');
                            $localRowIndex++;
                            
                            // Tutor 1 UVs
                            if ($creneau->tutor1 && $creneau->tutor1->proposedUvs->count() > 0) {
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'UVs proposées:');
                                
                                $uvs = $creneau->tutor1->proposedUvs->pluck('code')->sort()->implode(', ');
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(2 + $colOffset) . $localRowIndex, $uvs);
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(2 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                                $localRowIndex++;
                            }
                            
                            // Tutor 2
                            if ($creneau->tutor2) {
                                $tutor2Name = $creneau->tutor2->firstName . ' ' . $creneau->tutor2->lastName;
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'Tuteur 2: ' . $tutor2Name);
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                                $sheet->getStyle(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex)
                                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2EFDA');
                                $localRowIndex++;
                                
                                // Tutor 2 UVs
                                if ($creneau->tutor2->proposedUvs->count() > 0) {
                                    $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'UVs proposées:');
                                    
                                    $uvs = $creneau->tutor2->proposedUvs->pluck('code')->sort()->implode(', ');
                                    $sheet->setCellValue(Coordinate::stringFromColumnIndex(2 + $colOffset) . $localRowIndex, $uvs);
                                    $sheet->mergeCells(Coordinate::stringFromColumnIndex(2 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                                    $localRowIndex++;
                                }
                            }
                            
                            // Cellules vides pour s'alligner
                            while ($localRowIndex < $startRow + $maxHeaderRows) {
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, '');
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                                $localRowIndex++;
                            }
                            
                            // Header pour les tutee
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, 'Tutorés inscrits:');
                            $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                            $sheet->getStyle(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex)->getFont()->setBold(true);
                            $localRowIndex++;
                            
                            // Liste tous les tutee
                            $tuteeRowsWritten = 0;  
                            foreach ($creneau->inscriptions as $inscription) {
                                $tutee = $inscription->tutee;
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, $tutee->firstName . ' ' . $tutee->lastName);
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(2 + $colOffset) . $localRowIndex);
                                
                                // UVs du Tutee
                                $uvsSouhaites = collect(json_decode($inscription->enseignements_souhaites ?? '[]'))->sort()->implode(', ');
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(3 + $colOffset) . $localRowIndex, $uvsSouhaites);
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(3 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                                
                                $localRowIndex++;
                                $tuteeRowsWritten++;
                            }
                            
                            // Rangées vides pour s'alligner
                            while ($tuteeRowsWritten < $maxTuteeRows) {
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex, '');
                                $sheet->mergeCells(Coordinate::stringFromColumnIndex(1 + $colOffset) . $localRowIndex . ':' . Coordinate::stringFromColumnIndex(4 + $colOffset) . $localRowIndex);
                                $localRowIndex++;
                                $tuteeRowsWritten++;
                            }
                        }
                        
                        $totalHeight = $maxHeaderRows + 1 + $maxTuteeRows;
                        $rowIndex = $startRow + $totalHeight;
                        
                        if (!empty($creneauxGroup)) {
                            $borderStyle = [
                                'borders' => [
                                    'outline' => [
                                        'borderStyle' => Border::BORDER_MEDIUM,
                                        'color' => ['rgb' => '000000'],
                                    ],
                                ],
                            ];
                            
                            foreach ($creneauxGroup as $index => $creneau) {
                                $colStart = Coordinate::stringFromColumnIndex(1 + $index * 5);
                                $colEnd = Coordinate::stringFromColumnIndex(4 + $index * 5);
                                $sheet->getStyle($colStart . $startRow . ':' . $colEnd . ($rowIndex - 1))->applyFromArray($borderStyle);
                            }
                        }                    
                        $rowIndex++;
                    }
                }
                $rowIndex++;
            }
        }
        
        // Def première feuille active
        if ($spreadsheet->getSheetCount() > 0) {
            $spreadsheet->setActiveSheetIndex(0);
        }   
        
        // Réponse Excel
        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="creneaux_semestre.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}