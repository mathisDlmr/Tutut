<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\UV;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use App\Enums\Roles;

class TutorManageUvs extends Page implements Forms\Contracts\HasForms, Tables\Contracts\HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static string $view = 'filament.pages.tutor-manage-uvs';
    protected static ?string $title = 'Mes UVs proposées';

    public $selected_code;
    public $code;
    public $intitule;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, [
            Roles::Tutor,
            Roles::EmployedTutor,
            Roles::EmployedPrivilegedTutor,
        ]);
    }    

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Ajouter une UV existante')
                ->description('Choisissez une UV parmi celles déjà existantes')
                ->schema([
                    Forms\Components\Select::make('selected_code')
                        ->label('UV existante')
                        ->options(
                            \App\Models\UV::whereNotIn('code', Auth::user()->proposedUvs()->pluck('code'))
                                ->get()
                                ->mapWithKeys(fn ($uv) => [$uv->code => "{$uv->code} - {$uv->intitule}"])
                        )
                        ->searchable()
                        ->reactive()
                        ->requiredWithout(['code', 'intitule']),
                ]),
    
            Forms\Components\Section::make('OU créer une nouvelle UV')
                ->description('Créez une nouvelle UV avec son code et son intitulé')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Code de l’UV')
                        ->maxLength(10)
                        ->requiredWithout('selected_code'),
    
                    Forms\Components\TextInput::make('intitule')
                        ->label('Intitulé de l’UV')
                        ->maxLength(255)
                        ->requiredWithout('selected_code'),
                ])
                ->columns(2)
                ->visible(fn (callable $get) => !$get('selected_code')),
        ])->statePath('');
    }               

    public function createUv()
    {
        $data = $this->form->getState();
    
        if (!empty($data['selected_code'])) {
            $uv = UV::find($data['selected_code']);
        } else {
            $uv = UV::firstOrCreate(
                ['code' => $data['code']],
                ['intitule' => $data['intitule']]
            );
        }
    
        if ($uv) {
            Auth::user()->proposedUvs()->syncWithoutDetaching([$uv->code]);
            session()->flash('success', 'UV ajoutée à vos matières.');
        }
    
        $this->reset(['selected_code', 'code', 'intitule']);
        $this->form->fill();
    }     

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Auth::user()->proposedUvs()->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('intitule'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->action(function (UV $record) {
                        Auth::user()->proposedUvs()->detach($record->code);
                    }),
            ]);
    }
}