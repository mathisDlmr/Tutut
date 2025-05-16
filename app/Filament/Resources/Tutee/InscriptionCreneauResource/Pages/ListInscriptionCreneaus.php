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

/**
 * Page de liste des créneaux disponibles pour inscription
 * 
 * Cette page affiche les créneaux de tutorat auxquels les tutorés
 * peuvent s'inscrire, organisés en onglets par semaine et avec
 * des règles d'accès basées sur la date d'ouverture des inscriptions.
 */
class ListInscriptioncreneaus extends ListRecords
{
    protected static string $resource = InscriptionCreneauResource::class;
    
    /**
     * Définit les actions d'en-tête (vides pour cette ressource)
     * 
     * @return array Tableau d'actions
     */
    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
    
    /**
     * Récupère les paramètres d'inscription depuis le fichier de configuration
     * 
     * Lit les paramètres concernant la date et l'heure d'ouverture des
     * inscriptions pour les tutorés.
     * 
     * @return array Tableau associatif des paramètres d'inscription
     */
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
    
    /**
     * Détermine si la semaine actuelle et la semaine suivante doivent être affichées
     * 
     * Cette méthode vérifie, en fonction des paramètres de configuration,
     * si la date/heure actuelle permet aux tutorés de voir les créneaux
     * de la semaine suivante.
     * 
     * @return bool Vrai si les semaines actuelle et suivante doivent être affichées
     */
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
    
    /**
     * Définit les onglets pour la liste des créneaux d'inscription
     * 
     * Crée des onglets pour la semaine actuelle et éventuellement la semaine suivante
     * si la période d'inscription pour celle-ci est ouverte.
     * Chaque onglet affiche les créneaux d'une semaine spécifique, avec :
     * - Le numéro de semaine
     * - Un badge indiquant le nombre de créneaux disponibles
     * - Filtrage pour n'afficher que les créneaux pertinents (pas terminés, avec tuteurs)
     * 
     * @return array Tableau d'onglets configurés
     */
    public function getTabs(): array
    {
        $userId = Auth::id();
        $showNextWeek = $this->shouldShowCurrentAndNextWeek();
        
        $currentWeek = Semaine::where('date_debut', '<=', Carbon::now())
            ->where('date_fin', '>=', Carbon::now())
            ->first();
            
        $tabs = [];
        
        if ($currentWeek) {
            $tabs["semaine-{$currentWeek->id}"] = Tab::make(__('resources.inscription_creneau.semaine_actuelle')." ({$currentWeek->numero})")
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
                    $tabs["semaine-{$nextWeek->id}"] = Tab::make(__('resources.inscription_creneau.semaine_prochaine')." ({$nextWeek->numero})")
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