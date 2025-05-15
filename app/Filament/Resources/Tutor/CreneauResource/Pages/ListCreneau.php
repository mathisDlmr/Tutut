<?php
namespace App\Filament\Resources\Tutor\CreneauResource\Pages;

use App\Filament\Resources\Tutor\CreneauResource;
use App\Models\Creneaux;
use App\Models\Semaine;
use App\Enums\Roles;
use Carbon\Carbon;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ListCreneau extends ListRecords
{
    protected static string $resource = CreneauResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
    
    protected function shouldShowNextWeek(): bool
    {
        $user = Auth::user();
        $settings = $this->getRegistrationSettings();
        $now = Carbon::now();
        
        if ($user->role === Roles::Tutor->value) {
            $day = $settings['tutorRegistrationDay'] ?? 'friday';
            $time = $settings['tutorRegistrationTime'] ?? '16:00';
        } else {
            $day = $settings['employedTutorRegistrationDay'] ?? 'monday';
            $time = $settings['employedTutorRegistrationTime'] ?? '16:00';
        }
        
        $dayMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];
        
        $dayNumber = $dayMap[strtolower($day)] ?? 1;
        
        $registrationDate = Carbon::now()->startOfWeek()->addDays($dayNumber);
        
        $timeParts = explode(':', $time);
        $registrationDate->hour(intval($timeParts[0] ?? 0));
        $registrationDate->minute(intval($timeParts[1] ?? 0));
        $registrationDate->second(0);
        
        // Si on est après la date/heure d'inscription en fct du role, montrer la semaine suivante aussi
        return $now->greaterThanOrEqualTo($registrationDate);
    }
    
    protected function getRegistrationSettings(): array
    {
        $settingsPath = Storage::path('settings.json');
        if (file_exists($settingsPath)) {
            $settings = json_decode(file_get_contents($settingsPath), true);
            return $settings;
        }
        
        return [   // Valeurs par défaut si le fichier n'existe pas
            'employedTutorRegistrationDay' => 'monday',
            'employedTutorRegistrationTime' => '16:00',
            'tutorRegistrationDay' => 'friday',
            'tutorRegistrationTime' => '16:00',
        ];
    }
    
    public function getTabs(): array
    {
        $userId = Auth::id();
        $showNextWeek = $this->shouldShowNextWeek();
        
        $currentWeek = Semaine::where('date_debut', '<=', Carbon::now())
            ->where('date_fin', '>=', Carbon::now())
            ->first();
            
        $tabs = [];
        
        if ($currentWeek) {
            $tabs["semaine-{$currentWeek->id}"] = Tab::make("Semaine {$currentWeek->numero}")
                ->badge(fn() => Creneaux::where('fk_semaine', $currentWeek->id)->count())
                ->modifyQueryUsing(function (Builder $query) use ($currentWeek) {
                    return $query->where('fk_semaine', $currentWeek->id);
                });
                
            if ($showNextWeek) {
                $nextWeek = Semaine::where('numero', $currentWeek->numero + 1)
                    ->where('fk_semestre', $currentWeek->fk_semestre)
                    ->first();
                    
                if ($nextWeek) {
                    $tabs["semaine-{$nextWeek->id}"] = Tab::make("Semaine {$nextWeek->numero}")
                        ->badge(fn() => Creneaux::where('fk_semaine', $nextWeek->id)->count())
                        ->modifyQueryUsing(function (Builder $query) use ($nextWeek) {
                            return $query->where('fk_semaine', $nextWeek->id);
                        });
                }
            }
        }
        
        return $tabs;
    }
}