<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Creneaux;
use App\Models\Inscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $tutorCount = User::whereIn('role', ['tutor', 'employed_tutor'])->count();
        $employedTutorCount = User::where('role', 'employed_tutor')->count();
        $tuteeCount = User::where('role', 'tutee')->count();

        $totalCreneaux = Creneaux::count();
        $totalInscriptions = Inscription::count();
        $averageTuteesPerCreneaux = $totalCreneaux > 0 ? round($totalInscriptions / $totalCreneaux, 2) : 0;

        return [
            Stat::make('Nombre total de Tuteurs', $tutorCount),
            Stat::make('Tuteurs employés', $employedTutorCount),
            Stat::make('Nombre de Tutorés', $tuteeCount),
            Stat::make('Nombre de créneaux', $totalCreneaux),
            Stat::make('Moyenne des tuteurs par créneau', $averageTuteesPerCreneaux),
        ];
    }
}
