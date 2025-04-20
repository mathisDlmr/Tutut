<?php

namespace App\Filament\Widgets\Admin;

use App\Enums\Roles;
use App\Filament\Resources\FeedbackResource;
use App\Models\User;
use App\Models\Creneaux;
use App\Models\Feedback;
use App\Models\Inscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class DashboardStatsWidget extends BaseWidget
{
    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && $user->role === Roles::Administrator->value;
    }

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









protected function getResourcesForUser(): array
{
    $user = auth()->user();

    if (!$user || !$user->role instanceof Roles) {
        return [];
    }

    return match ($user->role) {
        Roles::Administrator => [
            TuteursEmployesResource::class,
            SemestreResource::class,
            SemaineResource::class,
            Salle::class,
        ],
        Roles::EmployedPrivilegedTutor => [
            SemestreResource::class,
            SemaineResource::class,
            Salle::class,
        ],
        Roles::EmployedTutor => [
        ],
        Roles::Tutor => [
        ],
        Roles::Tutee => [
        ],
        default => []
    };
}