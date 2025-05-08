<?php

namespace App\Filament\Resources\Tutor;

use App\Filament\Resources\Tutor\CreneauResource\Pages;
use App\Models\Creneaux;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use App\Enums\Roles;
use Filament\Forms\Components\Split;
use Illuminate\Support\Facades\Auth;

class CreneauResource extends Resource
{
    protected static ?string $model = Creneaux::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Shotgun Créneaux';
    protected static ?string $pluralModelLabel = 'Créneaux';
    protected static ?string $navigationGroup = 'Tutorat';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::EmployedPrivilegedTutor->value
            || Auth::user()->role === Roles::EmployedTutor->value
            || Auth::user()->role === Roles::Tutor->value);
    }    

    public static function form(Form $form): Form
    {
        return $form->schema([
            //
        ]);
    }
    
    public function getHoraireCompletAttribute(): string
    {
        return $this->start->format('H:i') . ' - ' . $this->end->format('H:i');
    }       

    public static function table(Table $table): Table
    {
        $userId = Auth::id();
    
        return $table
            ->query(
                Creneaux::query()
                    ->with([
                        'tutor1.proposedUvs:code,code', 
                        'tutor2.proposedUvs:code,code'
                    ])
                    ->where('open', true)
                    ->orderBy('start')
            )
            ->groups([
                Tables\Grouping\Group::make('day_and_time')
                    ->label('Jour et horaire')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn(Creneaux $record) =>
                        ucfirst($record->start->translatedFormat('l d F Y')) . ' - ' . 
                        $record->start->format('H:i') . ' à ' . $record->end->format('H:i')
                    )
                    ->getKeyFromRecordUsing(fn(Creneaux $record) => 
                        $record->start->format('Y-m-d') . '_' . $record->start->format('H:i')
                    )
                    ->collapsible(true),
            ])
            ->defaultGroup('day_and_time')
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    TextColumn::make('fk_salle')
                        ->label('Salle')
                        ->icon('heroicon-o-map-pin')
                        ->color('gray'),
    
                    Tables\Columns\Layout\Split::make([
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
    
                    TextColumn::make('id')
                        ->label('UVs proposées')
                        ->formatStateUsing(function ($state, Creneaux $creneau) {
                            $uvs = collect();
                    
                            if ($creneau->tutor1 && $creneau->tutor1->proposedUvs) {
                                $uvs = $uvs->merge($creneau->tutor1->proposedUvs->pluck('code'));
                            }
                    
                            if ($creneau->tutor2 && $creneau->tutor2->proposedUvs) {
                                $uvs = $uvs->merge($creneau->tutor2->proposedUvs->pluck('code'));
                            }
                    
                            return $uvs->unique()->sort()->implode(', ') ?: '—';
                        })
                        ->icon('heroicon-o-academic-cap')
                        ->color('primary'),                   
                ])
            ])
            ->contentGrid([
                'sm' => 2,
                'md' => 3,
                'lg' => 4,
                'xl' => 4,
            ])
            ->actions([
                Action::make('toggleShotgun1')
                    ->label(fn(Creneaux $record) => $record->tutor1_id === $userId ? 'Se désinscrire' : 'Shotgun 1')
                    ->color(fn(Creneaux $record) => $record->tutor1_id === $userId ? 'danger' : 'primary')
                    ->button()
                    ->visible(fn(Creneaux $record) =>
                        ($record->tutor1_id === null && $record->tutor2_id === null) ||$record->tutor1_id === $userId
                    )
                    ->action(function (Creneaux $record) use ($userId) {
                        if ($record->tutor1_id === $userId) {
                            $record->update(['tutor1_id' => null]);
                        } elseif (!$record->tutor1_id) {
                            $record->update(['tutor1_id' => $userId]);
                        }
                    }),
            
                Action::make('toggleShotgun2')
                    ->label(fn(Creneaux $record) => $record->tutor2_id === $userId ? 'Se désinscrire' : 'Shotgun 2')
                    ->color(fn(Creneaux $record) => $record->tutor2_id === $userId ? 'danger' : 'primary')
                    ->button()
                    ->visible(fn(Creneaux $record) =>
                        ($record->tutor1_id === null && $record->tutor2_id === null) ||
                        $record->tutor2_id === $userId
                    )
                    ->action(function (Creneaux $record) use ($userId) {
                        if ($record->tutor2_id === $userId) {
                            $record->update(['tutor2_id' => null]);
                        } elseif (!$record->tutor2_id) {
                            $record->update(['tutor2_id' => $userId]);
                        }
                    }),
            ])            
            ->paginated(false)
            ->recordUrl(null);
    }          

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreneau::route('/'),
        ];
    }
}

