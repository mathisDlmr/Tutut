<?php

namespace App\Filament\Resources\Admin\SalleResource\Pages;

use App\Filament\Resources\SalleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Salle;

class EditSalle extends EditRecord
{
    protected static string $resource = SalleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    } 

    private function parseCreneau($creneau): array
    {
        [$debut, $fin] = explode('-', $creneau);
    
        $normalizeTime = function ($time) {
            // Remplace 'h' par ':' et nettoie
            $time = trim(str_replace('h', ':', $time));
    
            // Si "12" ou "12:" → "12:00:00"
            if (preg_match('/^\d{1,2}$/', $time) || preg_match('/^\d{1,2}:$/', $time)) {
                $time = rtrim($time, ':') . ':00:00';
            }
    
            // Si "12:30" → "12:30:00"
            if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
                $time .= ':00';
            }
    
            // Si déjà bien formaté, on garde
            return $time;
        };
    
        return [$normalizeTime($debut), $normalizeTime($fin)];
    }    

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load('disponibilites');
    
        $dispos = [];
    
        foreach ($this->record->disponibilites as $dispo) {
            $formatHeure = fn($time) => \Carbon\Carbon::createFromFormat('H:i:s', $time)->format('H\hi');
            $creneauLabel = $formatHeure($dispo->debut) . '-' . $formatHeure($dispo->fin);
    
            if (!in_array($dispo->jour, ['Médians', 'Finaux'])) {
                $dispos[$dispo->jour][$creneauLabel] = true;
            }
        }
    
        // Ajout pour Médians / Finaux
        foreach (['Médians', 'Finaux'] as $jour) {
            $creneaux = $this->record->disponibilites->where('jour', $jour)->first();
            if ($creneaux) {
                $dispos[$jour]['debut'] = \Carbon\Carbon::createFromFormat('H:i:s', $creneaux->debut)->format('H:i');
                $dispos[$jour]['fin'] = \Carbon\Carbon::createFromFormat('H:i:s', $creneaux->fin)->format('H:i');
            }
        }
    
        $data['dispos'] = $dispos;
    
        return $data;
    }    

    protected function afterSave(): void
    {
        $this->record->disponibilites()->delete();
    
        $dispos = $this->form->getState()['dispos'] ?? [];
    
        foreach ($dispos as $jour => $creneaux) {
            // Traitement standard (checkboxes)
            foreach ($creneaux as $creneau => $isChecked) {
                if (in_array($jour, ['Médians', 'Finaux'])) {
                    // Skip Médians et Finaux ici, ils sont traités à part
                    continue;
                }
    
                if ($isChecked) {
                    [$debut, $fin] = $this->parseCreneau($creneau);
    
                    $this->record->disponibilites()->create([
                        'jour' => ucfirst($jour),
                        'debut' => $debut,
                        'fin' => $fin,
                    ]);
                }
            }
        }
    
        // Traitement spécifique pour Médians et Finaux
        foreach (['Médians', 'Finaux'] as $jour) {
            if (isset($dispos[$jour]['debut'], $dispos[$jour]['fin'])) {
                [$debut, $fin] = $this->parseCreneau($dispos[$jour]['debut'] . '-' . $dispos[$jour]['fin']);
    
                $this->record->disponibilites()->create([
                    'jour' => $jour,
                    'debut' => $debut,
                    'fin' => $fin,
                ]);
            }
        }
    }          
}
