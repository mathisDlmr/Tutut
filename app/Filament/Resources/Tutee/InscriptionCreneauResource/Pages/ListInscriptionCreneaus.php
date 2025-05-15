<?php

namespace App\Filament\Resources\Tutee\InscriptionCreneauResource\Pages;

use App\Filament\Resources\Tutee\InscriptionCreneauResource;
use App\Models\Creneaux;
use App\Models\Semaine;
use Carbon\Carbon;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ListInscriptioncreneaus extends ListRecords
{
    protected static string $resource = InscriptionCreneauResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
    
    protected function getRegistrationSettings(): array
    {
        $settingsPath = Storage::path('settings.json');
        if (file_exists($settingsPath)) {
            $settings = json_decode(file_get_contents($settingsPath), true);
            return $settings;
        }
        
        return [   // Valeurs par défaut si le fichier n'existe pas
            'tuteeRegistrationDay' => 'sunday',
            'tuteeRegistrationTime' => '16:00',
        ];
    }
    
    protected function shouldShowCurrentAndNextWeek(): bool
    {
        $settings = $this->getRegistrationSettings();
        
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
    
    public function getTabs(): array
    {
        $userId = Auth::id();
        $showNextWeek = $this->shouldShowCurrentAndNextWeek();
        
        $currentWeek = Semaine::where('date_debut', '<=', Carbon::now())
            ->where('date_fin', '>=', Carbon::now())
            ->first();
            
        $tabs = [];
        
        if ($currentWeek) {
            $tabs["semaine-{$currentWeek->id}"] = Tab::make("Semaine actuelle ({$currentWeek->numero})")
                ->badge(fn() => Creneaux::where('fk_semaine', $currentWeek->id)
                    ->where('end', '>', Carbon::now())
                    ->where(function ($query) {
                        $query->whereNotNull('tutor1_id')
                            ->orWhereNotNull('tutor2_id');
                    })
                    ->count())
                ->modifyQueryUsing(function (Builder $query) use ($currentWeek) {
                    return $query->where('fk_semaine', $currentWeek->id)
                        ->where('end', '>', Carbon::now())
                        ->where(function ($query) {
                            $query->whereNotNull('tutor1_id')
                                ->orWhereNotNull('tutor2_id');
                        });
                });
                
            if ($showNextWeek) {
                $nextWeek = Semaine::where('numero', $currentWeek->numero + 1)
                    ->where('fk_semestre', $currentWeek->fk_semestre)
                    ->first();
                    
                if ($nextWeek) {
                    $tabs["semaine-{$nextWeek->id}"] = Tab::make("Semaine prochaine ({$nextWeek->numero})")
                        ->badge(fn() => Creneaux::where('fk_semaine', $nextWeek->id)
                            ->where(function ($query) {
                                $query->whereNotNull('tutor1_id')
                                    ->orWhereNotNull('tutor2_id');
                            })
                            ->count())
                        ->modifyQueryUsing(function (Builder $query) use ($nextWeek) {
                            return $query->where('fk_semaine', $nextWeek->id)
                                ->where(function ($query) {
                                    $query->whereNotNull('tutor1_id')
                                        ->orWhereNotNull('tutor2_id');
                                });
                        });
                }
            }
        }
        
        return $tabs;
    }
}