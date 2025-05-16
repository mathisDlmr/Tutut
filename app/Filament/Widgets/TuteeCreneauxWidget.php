<?php

namespace App\Filament\Widgets;

use App\Models\Creneaux;
use App\Enums\Roles;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

class TuteeCreneauxWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Auth::user();
        return $user->role !== Roles::Administrator->value;
    }

    public function getHeading(): string
    {
        return __('resources.widgets.tutee_creneaux.heading');
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = Auth::user();

        return Creneaux::query()
            ->whereHas('inscriptions', fn($query) => $query->where('tutee_id', $user->id))
            ->where('end', '>=', now())
            ->where(function ($query) use ($user) {
                $query->whereNotNull('tutor1_id')
                    ->orWhereNotNull('tutor2_id');
                })
            ->with([
            'salle',
            'semaine',
            'tutor1',
            'tutor2',
            'inscriptions' => fn($q) => $q->where('tutee_id', $user->id),
            ])
            ->orderBy('start');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\Layout\Stack::make([
                TextColumn::make('start')
                    ->label(__('resources.widgets.tutee_creneaux.columns.day'))
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->formatStateUsing(fn($state, $record) =>
                        ucfirst($record->start->translatedFormat('l d F Y'))
                    ),

                Tables\Columns\Layout\Split::make([
                    TextColumn::make('start')
                        ->label(__('resources.widgets.tutee_creneaux.columns.schedule'))
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->formatStateUsing(fn($state, $record) =>
                            $record->start->format('H:i') . ' - ' . $record->end->format('H:i')
                        ),

                    TextColumn::make('salle.numero')
                        ->label(__('resources.widgets.tutee_creneaux.columns.room'))
                        ->icon('heroicon-o-map-pin')
                        ->color('gray'),
                ]),
                Tables\Columns\Layout\Split::make([
                    TextColumn::make('tutor1.firstName')
                        ->label(__('resources.widgets.tutee_creneaux.columns.tutor1'))
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->placeholder(__('resources.common.placeholders.none')),

                    TextColumn::make('tutor2.firstName')
                        ->label(__('resources.widgets.tutee_creneaux.columns.tutor2'))
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->placeholder(__('resources.common.placeholders.none')),
                ]),
                TextColumn::make('id')
                    ->label(__('resources.widgets.tutee_creneaux.columns.requested_courses'))
                    ->formatStateUsing(function ($state, Creneaux $creneau) {
                        $uvs = $creneau->inscriptions
                            ->flatMap(fn($inscription) => json_decode($inscription->enseignements_souhaites ?? '[]'))
                            ->filter()
                            ->unique()
                            ->sort()
                            ->values();

                        return $uvs->implode(', ') ?: __('resources.common.placeholders.none');
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
                ->label(__('resources.widgets.tutee_creneaux.columns.day'))
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
