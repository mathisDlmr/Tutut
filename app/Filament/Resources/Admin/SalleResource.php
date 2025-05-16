<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\SalleResource\Pages;
use App\Models\Salle;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use App\Enums\Roles;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SalleResource extends Resource
{
    protected static ?string $model = Salle::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    
    public static function getModelLabel(): string
    {
        return __('resources.admin.salle.label');
    }
    
    public static function getPluralModelLabel(): string
    {
        return __('resources.admin.salle.plural_label');
    }
    
    public static function getNavigationGroup(): string
    {
        return __('resources.admin.navigation_group.gestion');
    }
    
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::Administrator->value ||
               Auth::user()->role === Roles::EmployedPrivilegedTutor->value);
    }   

    public static function form(Form $form): Form
    {
        $jours = [
            __('resources.admin.salle.jours.lundi'), 
            __('resources.admin.salle.jours.mardi'), 
            __('resources.admin.salle.jours.mercredi'), 
            __('resources.admin.salle.jours.jeudi'), 
            __('resources.admin.salle.jours.vendredi'), 
            __('resources.admin.salle.jours.samedi'),
        ];
    
        $creneauxParJour = [
            __('resources.admin.salle.jours.lundi') => ['12h30-14h', '18h40-19h40', '19h40-20h40'],
            __('resources.admin.salle.jours.mardi') => ['12h30-14h', '18h40-19h40', '19h40-20h40'],
            __('resources.admin.salle.jours.mercredi') => ['12h30-14h', '18h40-19h40', '19h40-20h40'],
            __('resources.admin.salle.jours.jeudi') => ['12h30-14h', '18h40-19h40', '19h40-20h40'],
            __('resources.admin.salle.jours.vendredi') => ['12h30-14h', '18h40-19h40', '19h40-20h40'],
            __('resources.admin.salle.jours.samedi') => ['10h30-12h'],
            __('resources.admin.salle.jours.medians') => ['08h00-20h40'],
            __('resources.admin.salle.jours.finaux') => ['08h00-20h40'],
        ];        
    
        return $form
            ->schema([
                TextInput::make('numero')
                    ->label(__('resources.admin.salle.fields.numero'))
                    ->required()
                    ->unique(ignoreRecord: true),
    
                    Grid::make(2)
                    ->schema(
                        collect($creneauxParJour)->map(function ($creneaux, $jour) {
                            if (in_array($jour, [__('resources.admin.salle.jours.medians'), __('resources.admin.salle.jours.finaux')])) {
                                return Fieldset::make($jour)
                                    ->schema([
                                        TimePicker::make("dispos.$jour.debut")
                                            ->label(__('resources.admin.salle.fields.heure_debut'))
                                            ->seconds(false)
                                            ->required(),
                
                                        TimePicker::make("dispos.$jour.fin")
                                            ->label(__('resources.admin.salle.fields.heure_fin'))
                                            ->seconds(false)
                                            ->required(),
                                    ]);
                            }
                
                            return Fieldset::make($jour)
                                ->schema(
                                    collect($creneaux)->map(function ($creneau) use ($jour) {
                                        return Checkbox::make("dispos.$jour.$creneau")
                                            ->label($creneau);
                                    })->toArray()
                                );
                        })->values()->toArray()
                    )
            ]);
    }        
         
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')
                    ->label(__('resources.admin.salle.fields.numero')),

                TextColumn::make('disponibilites')
                    ->label(__('resources.admin.salle.fields.disponibilites'))
                    ->formatStateUsing(fn ($state, $record) =>
                        $record->disponibilites
                        ->groupBy('jour')
                        ->map(fn ($items, $jour) =>
                            $jour . ' : ' .
                            $items
                                ->map(fn ($item) => Carbon::createFromFormat('H:i:s', $item->debut)->format('H\hi') . '-' . Carbon::createFromFormat('H:i:s', $item->fin)->format('H\hi'))
                                ->join(', ')
                        )
                        ->join('<br>')                        
                    )
                    ->wrap()
                    ->limit(1000)
                    ->html()
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalles::route('/'),
            'create' => Pages\CreateSalle::route('/create'),
            'edit' => Pages\EditSalle::route('/{record}/edit'),
        ];
    }
}
