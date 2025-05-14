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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InscriptionCreneauResource extends Resource
{
    protected static ?string $model = Creneaux::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Créneaux disponibles';
    protected static ?string $pluralModelLabel = 'Créneaux disponibles';
    protected static ?int $navigationSort = 1;

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
            ->headerActions([
                Action::make('export_excel')
                    ->label('Exporter vers Excel')
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
            
        return $table;
    }          

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInscriptionCreneaus::route('/'),
        ];
    }
    
public static function exportExcel()
{
    $activeSemester = Semestre::getActive();
    if (!$activeSemester) {
        return response()->json(['error' => 'Aucun semestre actif trouvé'], 404);
    }
    
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('Système de Tutorat')
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