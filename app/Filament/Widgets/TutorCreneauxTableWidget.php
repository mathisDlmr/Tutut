<?php

namespace App\Filament\Widgets;

use App\Models\Creneaux;
use App\Enums\Roles;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

class TutorCreneauxTableWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Mes prochains créneaux';

    public static function canView(): bool
    {
        $user = Auth::user();
        return in_array($user->role, [
            Roles::Tutor->value,
            Roles::EmployedTutor->value,
            Roles::EmployedPrivilegedTutor->value,
        ]);
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = Auth::user();

        return Creneaux::query()
            ->with(['tutor1.proposedUvs', 'tutor2.proposedUvs', 'salle', 'semaine', 'inscriptions'])
            ->where(function ($query) use ($user) {
                $query->where('tutor1_id', $user->id)
                      ->orWhere('tutor2_id', $user->id);
            })
            ->orderBy('start');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\Layout\Stack::make([
                TextColumn::make('start')
                    ->label('Jour')
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->formatStateUsing(fn($state, $record) =>
                        ucfirst($record->start->translatedFormat('l d F Y'))
                    ),
                Split::make([
                    TextColumn::make('start')
                        ->label('Horaire')
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->formatStateUsing(fn($state, $record) =>
                            $record->start->format('H:i') . ' - ' . $record->end->format('H:i')
                        ),
                    TextColumn::make('salle.numero')
                        ->label('Salle')
                        ->icon('heroicon-o-map-pin')
                        ->color('gray'),
                ]),
                Split::make([
                    TextColumn::make('tutor1.firstName')
                        ->label('Tuteur 1')
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->placeholder('—'),
                    TextColumn::make('tutor2.firstName')
                        ->label('Tuteur 2')
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->placeholder('—'),
                ]),
                TextColumn::make('inscriptions_count')
                    ->label('Nombre d’inscrits')
                    ->counts('inscriptions')
                    ->icon('heroicon-o-users')
                    ->color('success'),

                TextColumn::make('id')
                    ->label('UVs demandées')
                    ->formatStateUsing(function ($state, Creneaux $creneau) {
                        $uvs = $creneau->inscriptions
                            ->flatMap(fn($inscription) => json_decode($inscription->enseignements_souhaites ?? '[]'))
                            ->filter()
                            ->unique()
                            ->sort()
                            ->values();

                        return $uvs->implode(', ') ?: '—';
                    })
                    ->icon('heroicon-o-academic-cap')
                    ->color('primary'),
            ]),
        ];
    }

    protected function getTableContentGrid(): ?array
    {
        return [
            'sm' => 2,
            'md' => 3,
            'lg' => 4,
            'xl' => 4,
        ];
    }

    protected function getTableGroups(): array
    {
        return [
            Tables\Grouping\Group::make('day')
                ->label('Jour')
                ->getTitleFromRecordUsing(fn(Creneaux $record) =>
                    ucfirst($record->start->translatedFormat('l d F Y'))
                )
                ->collapsible(false),
        ];
    }

    protected function getDefaultTableGroup(): ?string
    {
        return 'day';
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }
}
