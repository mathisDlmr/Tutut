<?php

namespace App\Filament\Widgets;

use App\Enums\Roles;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget;
use App\Models\Creneaux;
use App\Models\Inscription;
use App\Models\Comptabilite;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        $user = Auth::user();

        return in_array($user->role, [
            Roles::Administrator->value,
        ]);
    }

    protected function getStats(): array
    {
        $creneauxAvecInscriptions = Creneaux::withCount('inscriptions')
            ->whereHas('inscriptions')
            ->get();

        $totalInscriptions = $creneauxAvecInscriptions->sum('inscriptions_count');
        $totalCreneauxAvecInscrits = $creneauxAvecInscriptions->count();

        $moyenneParSoir = $creneauxAvecInscriptions
            ->groupBy(fn ($creneau) => $creneau->start->format('Y-m-d'))
            ->map(fn ($dayCreneaux) => $dayCreneaux->sum('inscriptions_count'))
            ->avg();

        $moyenneParCreneau = $totalCreneauxAvecInscrits > 0 ? $totalInscriptions / $totalCreneauxAvecInscrits : 0;

        $nbTutorésUniques = Inscription::distinct('tutee_id')->count();

        $creneauxParSemaine = Creneaux::where('open', true)
            ->where('tutor1_id', '!=', null)
            ->orWhere('tutor2_id', '!=', null)
            ->get()
            ->groupBy(fn ($creneau) => optional($creneau->semaine)->numero)
            ->map(fn ($group) => $group->count());

        $nbTuteursBénévoles = User::where('role', 'tutor')->count();

        $heuresTotales = Comptabilite::whereHas('user', function ($q) {
            $q->whereIn('role', ['tutor', 'employedTutor', 'employedPrivilegedTutor']);
        })->get()->sum('nb_heures');

        $topUVs = Inscription::get()
            ->flatMap(fn ($inscription) => json_decode($inscription->enseignements_souhaites ?? '[]'))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->map(fn ($count, $code) => "$code ($count)")
            ->implode(', ');

        return [
            Stat::make('Moyenne de tutoré.e.s / soir', number_format($moyenneParSoir, 1)),
            Stat::make('Moyenne de tutoré.e.s / créneau', number_format($moyenneParCreneau, 1)),
            Stat::make('Nombre total de tutoré.e.s actif.ve sur le semestre', $nbTutorésUniques),
            Stat::make('Créneaux ouverts / semaine', $creneauxParSemaine->avg() ? round($creneauxParSemaine->avg(), 2) : 0),
            Stat::make('Tuteurs bénévoles', $nbTuteursBénévoles),
            Stat::make('Heures données (comptabilisées)', round($heuresTotales, 1) . 'h'),
            Stat::make('UVs les plus demandées', $topUVs ?: '—'),
        ];
    }
}

