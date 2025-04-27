<?php

namespace App\Filament\Resources\Tutor\ComptabiliteResource\Pages;

use App\Filament\Resources\Tutor\ComptabiliteResource;
use App\Models\Creneaux;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateComptabilite extends CreateRecord
{
    protected static string $resource = ComptabiliteResource::class;


    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('cancel')
                ->label('Annuler')
                ->url($this->previousUrl ?? static::getResource()::getUrl())
                ->color('gray'),
    
            \Filament\Actions\Action::make('save')
                ->label('Enregistrer')
                ->submit('save')
                ->color('primary'),
        ];
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

    public function create(bool $another = false): void
    {
        $user = Auth::user();
    
        $creneaux = Creneaux::where(function ($q) use ($user) {
                $q->where('tutor1_id', $user->id)
                  ->where('tutor1_compted', true)
                  ->orWhere('tutor2_id', $user->id)
                  ->where('tutor2_compted', true);
            })
            ->whereHas('inscriptions')
            ->get();
    
        $creneauxParSemaine = $creneaux->groupBy('fk_semaine');
        $formState = $this->form->getState(); 
        $semestreActif = \App\Models\Semestre::where('is_active', true)->first();
        $semaines = \App\Models\Semaine::where('fk_semestre', $semestreActif->code)->get();
    
        foreach ($semaines as $semaine) {
            $heuresSupp = collect($formState["heures_supplementaires_{$semaine->id}"] ?? []);
            $creneaux = $creneauxParSemaine[$semaine->id] ?? collect();
            
            $totalMinutes = $creneaux->sum(fn ($creneau) => $creneau->start->diffInMinutes($creneau->end));
            $heuresSuppTotal = $heuresSupp->sum('nb_heures') ?? 0;
            $nb_heures = ($totalMinutes / 60) + $heuresSuppTotal;
    
            if ($nb_heures > 0) {
                \App\Models\Comptabilite::updateOrCreate(
                    [
                        'fk_user' => $user->id,
                        'fk_semaine' => $semaine->id,
                    ],
                    [
                        'nb_heures' => $nb_heures,
                    ]
                );
            }
    
            \App\Models\HeuresSupplementaires::where('user_id', $user->id)
                ->where('semaine_id', $semaine->id)
                ->delete();
    
            foreach ($heuresSupp as $heureSupp) {
                \App\Models\HeuresSupplementaires::create([
                    'user_id' => $user->id,
                    'semaine_id' => $semaine->id,
                    'nb_heures' => $heureSupp['nb_heures'],
                    'commentaire' => $heureSupp['commentaire'],
                ]);
            }
        }
    
        Notification::make()
            ->title('Heures mises à jour')
            ->success()
            ->send();
    
        $this->redirect(ComptabiliteResource::getUrl('index'));
    }                   
}