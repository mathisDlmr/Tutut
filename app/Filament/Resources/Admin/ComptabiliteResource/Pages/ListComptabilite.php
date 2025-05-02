<?php

namespace App\Filament\Resources\Admin\ComptabiliteResource\Pages;

use App\Filament\Resources\Admin\ComptabiliteResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use App\Models\Semestre;
use App\Models\Semaine;
use App\Models\User;
use App\Models\Comptabilite;
use App\Enums\Roles;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ListComptabilite extends ListRecords
{
    protected static string $resource = ComptabiliteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Exporter CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $semestreActif = Semestre::where('is_active', true)->first();
                    
                    if (!$semestreActif) {
                        $this->notify('danger', 'Aucun semestre actif trouvé.');
                        return;
                    }
                    
                    $semaines = Semaine::where('fk_semestre', $semestreActif->code)
                        ->orderBy('numero')
                        ->get();
                    
                    $employedTutorIds = DB::table('comptabilite')
                        ->whereIn('fk_semaine', $semaines->pluck('numero'))
                        ->pluck('fk_user')
                        ->merge(
                            DB::table('heures_supplementaires')
                                ->whereIn('fk_semaine', $semaines->pluck('numero'))
                                ->pluck('fk_user')
                        )
                        ->unique();
                        
                    $employedTutors = User::whereIn('id', $employedTutorIds)
                        ->whereIn('role', [
                            Roles::EmployedTutor->value, 
                            Roles::EmployedPrivilegedTutor->value
                        ])
                        ->orderBy('lastName')
                        ->orderBy('firstName')
                        ->get();
                    
                    $csvData = [];
                    
                    $header = ['Nom', 'Prénom', 'Email'];
                    foreach ($semaines as $semaine) {
                        $header[] = "Semaine {$semaine->numero}";
                    }
                    $header[] = "Total";
                    
                    $csvData[] = $header;
                    
                    foreach ($employedTutors as $tutor) {
                        $row = [
                            $tutor->lastName,
                            $tutor->firstName,
                            $tutor->email,
                        ];
                        
                        $total = 0;
                        
                        foreach ($semaines as $semaine) {
                            $comptabilite = Comptabilite::where('fk_user', $tutor->id)
                                ->where('fk_semaine', $semaine->numero)
                                ->first();
                            
                            $heuresSemaine = ($comptabilite ? $comptabilite->nb_heures : 0);
                            $total += $heuresSemaine;
                            
                            $row[] = $heuresSemaine;
                        }
                        
                        $row[] = $total;
                        $csvData[] = $row;
                    }
                    
                    $csvContent = '';
                    foreach ($csvData as $row) {
                        $escapedRow = array_map(function($value) {
                            return '"' . str_replace('"', '""', $value) . '"';
                        }, $row);
                        
                        $csvContent .= implode(',', $escapedRow) . "\n";
                    }
                    
                    $csvContent = chr(0xEF) . chr(0xBB) . chr(0xBF) . $csvContent;                    
                    $filename = "comptabilite_tuteurs_{$semestreActif->code}.csv";
                    
                    return response()->streamDownload(function () use ($csvContent) {
                        echo $csvContent;
                    }, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                })
        ];
    }
}