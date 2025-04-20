<?php

namespace App\Filament\Resources\Tutor\CreneauResource\Pages;

use App\Filament\Resources\Tutor\CreneauResource;
use App\Models\Creneaux;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Filament\Support\Colors\Color;

class ShotgunCreneaux extends Page
{
    protected static string $resource = CreneauResource::class;
    protected static string $view = 'filament.resources.creneau-resource.pages.shotgun-creneaux';

    public $groupedCreneaux = [];

    public function mount(): void
    {
        $this->groupedCreneaux = Creneaux::with(['semaine', 'tutor1', 'tutor2'])
            ->where('open', true)
            ->orderBy('start')
            ->get();
    }    

    public function shotgun(int $creneauId, int $position): void
    {
        $creneau = Creneaux::findOrFail($creneauId);
        
        if ($position === 1 && !$creneau->tutor1_id) {
            $creneau->tutor1_id = Auth::id();
        }
    
        if ($position === 2 && !$creneau->tutor2_id && $creneau->tutor1_id !== Auth::id()) {
            $creneau->tutor2_id = Auth::id();
        }
    
        $creneau->save();
    
        $this->mount(); // recharger les données
        $this->dispatch('notify', type: 'success', message: 'Shotgun réussi');
    }    
}
